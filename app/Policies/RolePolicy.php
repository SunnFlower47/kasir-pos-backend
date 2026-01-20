<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Role; // Use our custom Role model

class RolePolicy
{
    /**
     * Determine if the user can view the role.
     */
    public function view(User $user, Role $role): bool
    {
        // Tenant Access Scope handles filtering, but explicitly:
        if ($user->tenant_id) {
            // Can view if it's their own or a template
            return ($role->tenant_id === $user->tenant_id) || 
                   ($role->scope === 'tenant' && is_null($role->tenant_id));
        }
        return true; // System admin sees all
    }

    /**
     * Determine if the user can assign the role to another user.
     */
    public function assign(User $user, Role $targetRole): bool
    {
        // 1. System Admin (no tenant_id) can assign anything
        if (!$user->tenant_id) {
            return true;
        }

        // 2. Tenant User CANNOT assign 'system' scope roles
        if ($targetRole->scope === 'system') {
            return false;
        }

        // 3. Tenant User CANNOT assign roles from other tenants
        if ($targetRole->tenant_id && $targetRole->tenant_id !== $user->tenant_id) {
            return false;
        }

        // 4. Tenant User CAN assign if it's their own or a global 'tenant' template
        return true;
    }

    // Other standard policy methods (create, update, delete) can be added here
    public function create(User $user): bool
    {
        // Only allow if user has permission 'roles.create'? 
        // RBAC logic usually handled by Gates, but scoping here is good too.
        return $user->hasPermissionTo('roles.create'); // Assuming you have this permission
    }
}
