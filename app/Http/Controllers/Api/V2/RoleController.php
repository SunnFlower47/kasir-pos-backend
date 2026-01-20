<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     * Tenants see their own roles + Global Template Roles (Owner, Cashier, etc.)
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // Policy check usually handles this via index authorization or just scope
        if (!$user->can('roles.view')) {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Explicitly filter roles for the current tenant.
        // We removed Global Scope to prevent Spatie Cache corruption, so we MUST filter here.
        $query = Role::with('permissions')->withCount('users');
        
        if ($user->tenant_id) {
            $query->where('tenant_id', $user->tenant_id);
        }
        
        $roles = $query->get();
        
        // Append permission count for overview
        $roles->map(function($role) {
            $role->permissions_count = $role->permissions()->count();
            return $role;
        });

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user->can('roles.create')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => [
                'required', 
                'string', 
                'max:255',
                // Unique check needs to be scoped to tenant to allow "Cashier" in Tenant A and Tenant B
                Rule::unique('roles')->where(function ($query) use ($user) {
                    return $query->where('tenant_id', $user->tenant_id)
                                 ->where('guard_name', 'sanctum');
                })
            ],
            'description' => 'nullable|string',
            'permissions' => 'array', // List of permission names or IDs
            'permissions.*' => 'exists:permissions,name' // Input assuming names often easier for frontend
        ]);

        // Create Role
        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'sanctum',
            'tenant_id' => $user->tenant_id, // Automatically assign to creator's tenant
            'scope' => 'tenant',
            'description' => $request->description,
        ]);

        // Assign Permissions
        if ($request->has('permissions')) {
            // Filter permissions: Tenant users can only assign 'tenant' scope permissions
            $validPermissions = Permission::whereIn('name', $request->permissions)
                ->where('scope', 'tenant')
                ->get();
            
            $role->syncPermissions($validPermissions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role->load('permissions')
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        $user = Auth::user();
        
        // Policy check: prevent viewing system roles if not system admin
        if ($user->cannot('view', $role)) {
            return response()->json(['message' => 'Unauthorized to view this role'], 403);
        }
        
        // Eager load permissions
        $role->load('permissions');

        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $user = Auth::user();

        // 1. Check Permission to edit roles
        if (!$user->can('roles.edit')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2. Policy Check: Can ONLY edit own roles, NOT templates
        // If Role tenant_id IS NULL (Global Template), Tenant cannot edit it.
        if (is_null($role->tenant_id) && !$user->hasRole('System Admin')) {
             return response()->json([
                 'success' => false,
                 'message' => 'Cannot edit global system roles. Please clone/create a new role instead.'
             ], 403);
        }

        // 3. Prevent editing other tenant's role (handled by implicit model binding + Global Scope normally, but good to be explicit)
        if ($role->tenant_id && $role->tenant_id !== $user->tenant_id) {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => [
                'required', 
                'string', 
                'max:255',
                Rule::unique('roles')->where(function ($query) use ($user) {
                    return $query->where('tenant_id', $user->tenant_id)
                                 ->where('guard_name', 'sanctum');
                })->ignore($role->id)
            ],
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,name'
        ]);

        $role->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);

        if ($request->has('permissions')) {
             $validPermissions = Permission::whereIn('name', $request->permissions)
                ->where('scope', 'tenant')
                ->get();
             
             $role->syncPermissions($validPermissions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role->load('permissions')
        ]);
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy(Role $role): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user->can('roles.delete')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 1. Prevent deleting Global Templates
        if (is_null($role->tenant_id) && !$user->hasRole('System Admin')) {
             return response()->json([
                 'success' => false,
                 'message' => 'Cannot delete global system roles.'
             ], 403);
        }
        
        // 2. Prevent deleting other tenant's roles
        if ($role->tenant_id && $role->tenant_id !== $user->tenant_id) {
             return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 3. Prevent deleting if has users active? 
        // Optional safety:
        if ($role->users()->count() > 0) {
            return response()->json([
                'success' => false, 
                'message' => 'Cannot delete role because it is assigned to users.'
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }
}
