<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class TenantOnboardingService
{
    /**
     * Register a new tenant with user, roles, and default outlet.
     * Wrapped in a transaction to ensure atomicity.
     * Explicitly clears Spatie cache permissions upon completion.
     */
    public function registerTenant(array $data)
    {
        return DB::transaction(function () use ($data) {
            // 1. Create Tenant
            $tenant = Tenant::create([
                'name' => $data['company_name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'owner_name' => $data['name'],
                'is_active' => true,
            ]);

            // 2. Create Default Outlet
            $outlet = Outlet::create([
                'tenant_id' => $tenant->id,
                'name' => 'Outlet Pusat',
                'code' => 'PUSAT-' . $tenant->id,
                'address' => $data['address'] ?? 'Alamat Utama',
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            // 3. Create Super Admin User
            $user = User::create([
                'tenant_id' => $tenant->id,
                'outlet_id' => $outlet->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'phone' => $data['phone'] ?? null,
                'is_active' => true,
            ]);

            // 4. Clone Roles from Templates
            $ownerRole = $this->cloneRolesForTenant($tenant);

            // 5. Assign Role to User
            if ($ownerRole) {
                // Ensure the relationship is refreshed before assignment to avoid cache issues within transaction
                $user->assignRole($ownerRole);
            }

            // 6. Create Subscription (Inactive logic)
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_name' => 'free',
                'status' => 'inactive', 
                'price' => 0,
                'period' => 'monthly',
                'start_date' => Carbon::now(),
                'max_outlets' => 1,
            ]);

             // 7. CRITICAL: Invalidate Spatie Cache to ensure new roles/permissions are visible immediately.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return [
                'tenant' => $tenant,
                'user' => $user,
            ];
        });
    }

    /**
     * Clones all 'tenant' scoped roles for the new tenant.
     * Returns the 'Owner' role instance for assignment.
     */
    protected function cloneRolesForTenant(Tenant $tenant): ?Role
    {
        // Fetch templates: roles with no tenant_id (global templates) and scope='tenant'
        $templateRoles = Role::withoutGlobalScopes()
            ->whereNull('tenant_id')
            ->where('scope', 'tenant')
            ->get();

        $ownerRole = null;

        foreach ($templateRoles as $template) {
            $newRole = new Role();
            $newRole->fill([
                'name' => $template->name,
                'guard_name' => 'sanctum',
                'tenant_id' => $tenant->id,
                'scope' => 'tenant',
                'description' => $template->description ?? ($template->name . ' role for ' . $tenant->name),
            ]);
            $newRole->save();

            // Sync permissions from template
            $newRole->syncPermissions($template->permissions);

            if ($template->name === 'Owner') {
                $ownerRole = $newRole;
            } elseif ($template->name === 'Super Admin' && !$ownerRole) {
                $ownerRole = $newRole;
            }
        }

        // Fallback if no templates exist
        if (!$ownerRole) {
            $ownerRole = new Role();
            $ownerRole->fill([
                'name' => 'Owner',
                'guard_name' => 'sanctum',
                'tenant_id' => $tenant->id,
                'scope' => 'tenant',
            ]);
            $ownerRole->save();
        }

        return $ownerRole;
    }
}
