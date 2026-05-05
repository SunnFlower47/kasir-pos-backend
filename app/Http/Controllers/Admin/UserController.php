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
        // Guard-aware role list to prevent assigning wrong-guard roles
        if ($user->tenant_id) {
            // Tenant users should use API/auth roles (sanctum)
            $roles = Role::where('guard_name', 'sanctum')->orderBy('name')->get();
        } else {
            // System users in admin panel should use web guard roles
            $roles = Role::where('guard_name', 'web')->orderBy('name')->get();
        }

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
            'role' => 'nullable|string|exists:roles,name',
        ]);

        // Guard-aware target role resolution
        $roleName = $request->input('role');
        $targetRole = null;

        if ($roleName) {
            $expectedGuard = $user->tenant_id ? 'sanctum' : 'web';
            $targetRole = Role::where('name', $roleName)
                ->where('guard_name', $expectedGuard)
                ->first();

            if (!$targetRole) {
                return back()->withErrors([
                    'role' => "Role '{$roleName}' tidak tersedia untuk guard '{$expectedGuard}'."
                ])->withInput();
            }

            // Extra safety: Prevent assigning system-scoped role to tenant user
            if ($user->tenant_id && isset($targetRole->scope) && $targetRole->scope === 'system') {
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

        if ($targetRole) {
            // Pass Role model to avoid guard mismatch lookup by name
            $user->syncRoles([$targetRole]);
        }

        AuditLog::createLog('App\Models\User', $user->id, 'update_by_admin', null, $data);

        return redirect()->route('admin.users.index')->with('success', 'User updated successfully.');
    }

    // Destroy, etc.
}
