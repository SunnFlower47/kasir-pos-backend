<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Validation\Rule;
use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * Display a listing of global roles.
     */
    public function index()
    {
        // Show all Global Roles (no tenant_id)
        // We might also want to show Tenant Templates if they are stored as global roles with scope='tenant'
        $roles = Role::whereNull('tenant_id')
                    ->orderBy('scope')
                    ->orderBy('name')
                    ->paginate(10);
                    
        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new role.
     */
    public function create()
    {
        return view('admin.roles.create');
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'scope' => 'required|in:system,tenant',
            'description' => 'nullable|string|max:1000',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'sanctum', // Default for API
            'scope' => $validated['scope'],
            'description' => $validated['description'],
            'tenant_id' => null, // Always global
        ]);

        AuditLog::createLog('Spatie\Permission\Models\Role', $role->id, 'create', null, $role->toArray());

        return redirect()->route('admin.roles.index')->with('success', 'Role created successfully.');
    }

    /**
     * Show the form for editing the specified role permissions.
     */
    public function edit(Role $role)
    {
        if ($role->tenant_id !== null) {
            abort(403, 'Cannot edit tenant-specific roles from Global Manager.');
        }

        // Group permissions for better UI
        $permissions = Permission::all()->groupBy(function($perm) {
            return explode('.', $perm->name)[0]; // Group by prefix (users, products, etc)
        });

        // Load role permissions
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('admin.roles.edit', compact('role', 'permissions', 'rolePermissions'));
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role)
    {
        if ($role->tenant_id !== null) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles')->ignore($role->id)],
            'description' => 'nullable|string|max:1000',
            'permissions' => 'array', // Array of permission names
            'permissions.*' => 'exists:permissions,name'
        ]);

        // Prevent renaming Critical Roles if needed, but allow for now.
        $role->update([
            'name' => $validated['name'],
            'description' => $validated['description'],
        ]);

        $permissions = $request->input('permissions', []);
        $role->syncPermissions($permissions);

        AuditLog::createLog('Spatie\Permission\Models\Role', $role->id, 'update', null, ['permissions_count' => count($permissions)]);

        return redirect()->route('admin.roles.index')->with('success', 'Role updated successfully.');
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role)
    {
        if ($role->tenant_id !== null) {
            abort(403, 'Unauthorized.');
        }

        // Prevent deleting critical system roles
        if (in_array($role->name, ['System Admin', 'Owner', 'Super Admin'])) {
            return back()->with('error', 'Cannot delete core system roles.');
        }

        if ($role->users()->count() > 0) {
            return back()->with('error', 'Cannot delete role assigned to users.');
        }

        $role->delete();
        AuditLog::createLog('Spatie\Permission\Models\Role', $role->id, 'delete');

        return redirect()->route('admin.roles.index')->with('success', 'Role deleted successfully.');
    }
}
