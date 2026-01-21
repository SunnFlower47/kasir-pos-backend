<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Models\AuditLog;

class UserController extends Controller
{
    /**
     * Display a listing of ALL users.
     */
    public function index(Request $request)
    {
        $query = User::with(['roles', 'outlet', 'tenant']); // Assuming tenant relationship exists or user has tenant_id

        // Filters
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('role')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }

        if ($request->has('type')) {
            if ($request->type === 'system') {
                $query->whereNull('tenant_id');
            } elseif ($request->type === 'tenant') {
                $query->whereNotNull('tenant_id');
            }
        }

        $users = $query->paginate(15);
        $roles = Role::pluck('name'); // Filter options

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for editing the specified user.
     */
    public function edit(User $user)
    {
        // We can edit System users fully.
        // For Tenant users, we should be careful. Maybe only specific fields?
        // But System Admin implies Root access, so generally full edit is allowed but warned.
        
        $roles = Role::all(); // Global roles
        
        // If editing a tenant user, maybe show explanation that they are managed by tenant?
        // But for support reasons, we allow editing.

        return view('admin.users.edit', compact('user', 'roles'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'boolean',
             // Role update logic is tricky. 
             // If System User -> assign System Roles.
             // If Tenant User -> usually managed by Tenant. But SysAdmin can override?
             // Let's allow SysAdmin to assign Global Roles only if user is System.
             // If user is Tenant, changing role might break their access if role is 'system' scope.
        ]);

        // Security: Prevent assigning System Role to Tenant User
        $roleName = $request->input('role');
        if ($roleName && $user->tenant_id) {
             $targetRole = Role::where('name', $roleName)->first();
             if ($targetRole && $targetRole->scope === 'system') {
                 return back()->with('error', 'Cannot assign System Role to a Tenant User.');
             }
        }

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $request->has('is_active') ? $validated['is_active'] : $user->is_active,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        if ($roleName) {
            $user->syncRoles([$roleName]);
        }

        AuditLog::createLog('App\Models\User', $user->id, 'update_by_admin', null, $data);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    // Destroy, etc.
}
