<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddAuditLogPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Audit Log permissions
        $auditLogPermissions = [
            'audit-logs.view',
            'audit-logs.delete',
        ];

        foreach ($auditLogPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Also create for Sanctum guard
        foreach ($auditLogPermissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum'
            ]);
        }

        // Assign permissions to roles
        // Super Admin - Full access (already has all permissions via syncPermissions)
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo($auditLogPermissions);
            
            // Also for Sanctum guard
            $superAdminSanctum = Role::where('name', 'Super Admin')
                ->where('guard_name', 'sanctum')
                ->first();
            if ($superAdminSanctum) {
                $superAdminSanctum->givePermissionTo(
                    Permission::whereIn('name', $auditLogPermissions)
                        ->where('guard_name', 'sanctum')
                        ->get()
                );
            }
        }

        // Admin - Can view audit logs
        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $admin->givePermissionTo(['audit-logs.view']);
            
            // Also for Sanctum guard
            $adminSanctum = Role::where('name', 'Admin')
                ->where('guard_name', 'sanctum')
                ->first();
            if ($adminSanctum) {
                $adminSanctum->givePermissionTo(
                    Permission::where('name', 'audit-logs.view')
                        ->where('guard_name', 'sanctum')
                        ->first()
                );
            }
        }

        echo "Audit Log permissions added successfully!\n";
    }
}

