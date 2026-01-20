<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SystemAdminController extends Controller
{
    public function index()
    {
        // System Admins have no tenant_id
        $users = User::whereNull('tenant_id')->with('roles')->paginate(10);
        return view('admin.system-admins.index', compact('users'));
    }

    public function create()
    {
        $roles = Role::where('scope', 'system')->get();
        return view('admin.system-admins.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'tenant_id' => null, // Explicitly null for system admin
            'is_active' => true,
        ]);

        $user->assignRole($validated['role']);
        
        \App\Models\AuditLog::createLog('App\Models\User', $user->id, 'create_admin', null, ['role' => $validated['role']]);

        return redirect()->route('admin.system-admins.index')->with('success', 'System Admin created and role assigned.');
    }

    public function edit(User $user)
    {
        // Prevent editing non-system users via this controller
        if ($user->tenant_id !== null) {
            abort(403, 'Unauthorized action.');
        }

        $roles = Role::where('scope', 'system')->get();
        return view('admin.system-admins.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        if ($user->tenant_id !== null) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|exists:roles,name',
            'is_active' => 'boolean'
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $request->has('is_active') ? $validated['is_active'] : $user->is_active,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);
        $user->syncRoles([$validated['role']]);
        
        \App\Models\AuditLog::createLog('App\Models\User', $user->id, 'update_admin', null, ['role' => $validated['role']]);

        return redirect()->route('admin.system-admins.index')->with('success', 'System Admin updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->tenant_id !== null) {
            abort(403, 'Unauthorized action.');
        }
        
        // Prevent deleting self
        if (auth()->id() === $user->id) {
            return back()->with('error', 'Cannot delete yourself.');
        }

        $user->delete();
        \App\Models\AuditLog::createLog('App\Models\User', $user->id, 'delete_admin');
        return redirect()->route('admin.system-admins.index')->with('success', 'System Admin deleted successfully.');
    }
}
