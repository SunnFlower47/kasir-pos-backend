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
                'business_type' => $data['business_type'] ?? null,
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

            // 4. Assign Global Owner Role (No Cloning)
            $ownerRole = Role::where('name', 'Owner')
                ->where('scope', 'tenant')
                ->first();
            
            if ($ownerRole) {
                // Ensure the relationship is refreshed before assignment to avoid cache issues within transaction
                $user->assignRole($ownerRole);
            } else {
                 // Fallback: This shouldn't happen if seeder ran, but handled gracefully?
                 // Log error or throw exception in production
            }

            // 5. Create Subscription (Inactive logic)
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_name' => 'free',
                'status' => 'inactive', 
                'price' => 0,
                'period' => 'monthly',
                'start_date' => Carbon::now(),
                'max_outlets' => 1,
            ]);

            // 6. CRITICAL: Invalidate Spatie Cache to ensure new roles/permissions are visible immediately.
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return [
                'tenant' => $tenant,
                'user' => $user,
            ];
        });
    }

    /**
     * Helper to get global owner role (Optional, logic is inline now but kept if needed for interface compatibility)
     */
    protected function getGlobalOwnerRole(): ?Role
    {
        return Role::where('name', 'Owner')->where('scope', 'tenant')->first();
    }
}
