<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AddExportImportPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder adds export/import permissions to existing roles
     */
    public function run(): void
    {
        // Create new permissions
        $newPermissions = [
            'export.view',
            'export.manage',
            'import.view',
            'import.create',
            'import.manage',
        ];

        foreach ($newPermissions as $permission) {
            // Create for default guard
            Permission::firstOrCreate(['name' => $permission]);

            // Create for sanctum guard
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum'
            ]);
        }

        // Assign to roles for default guard
        $superAdmin = Role::where('name', 'Super Admin')->first();
        $admin = Role::where('name', 'Admin')->first();
        $manager = Role::where('name', 'Manager')->first();

        if ($superAdmin) {
            // Super Admin already has all permissions via syncPermissions(Permission::all())
            // Just ensure new permissions are assigned
            $superAdmin->givePermissionTo($newPermissions);
        }

        if ($admin) {
            $adminPermissions = ['export.view', 'export.manage', 'import.view', 'import.create', 'import.manage'];
            $admin->givePermissionTo($adminPermissions);
        }

        if ($manager) {
            $managerPermissions = ['export.view', 'export.manage', 'import.view', 'import.create', 'import.manage'];
            $manager->givePermissionTo($managerPermissions);
        }

        // Assign to roles for sanctum guard
        $superAdminSanctum = Role::where('name', 'Super Admin')->where('guard_name', 'sanctum')->first();
        $adminSanctum = Role::where('name', 'Admin')->where('guard_name', 'sanctum')->first();
        $managerSanctum = Role::where('name', 'Manager')->where('guard_name', 'sanctum')->first();

        $sanctumPermissions = array_map(function($perm) {
            return Permission::where('name', $perm)->where('guard_name', 'sanctum')->first();
        }, $newPermissions);
        $sanctumPermissions = array_filter($sanctumPermissions);

        if ($superAdminSanctum) {
            $superAdminSanctum->givePermissionTo($sanctumPermissions);
        }

        if ($adminSanctum) {
            $adminSanctumPermissions = [
                Permission::where('name', 'export.view')->where('guard_name', 'sanctum')->first(),
                Permission::where('name', 'export.manage')->where('guard_name', 'sanctum')->first(),
                Permission::where('name', 'import.view')->where('guard_name', 'sanctum')->first(),
                Permission::where('name', 'import.create')->where('guard_name', 'sanctum')->first(),
                Permission::where('name', 'import.manage')->where('guard_name', 'sanctum')->first(),
            ];
            $adminSanctumPermissions = array_filter($adminSanctumPermissions);
            $adminSanctum->givePermissionTo($adminSanctumPermissions);
        }

        if ($managerSanctum) {
            $managerSanctumPermissions = [
                Permission::where('name', 'export.view')->where('guard_name', 'sanctum')->first(),
                Permission::where('name', 'export.manage')->where('guard_name', 'sanctum')->first(),
                Permission::where('name', 'import.view')->where('guard_name', 'sanctum')->first(),
                Permission::where('name', 'import.create')->where('guard_name', 'sanctum')->first(),
                Permission::where('name', 'import.manage')->where('guard_name', 'sanctum')->first(),
            ];
            $managerSanctumPermissions = array_filter($managerSanctumPermissions);
            $managerSanctum->givePermissionTo($managerSanctumPermissions);
        }

        echo "Export/Import permissions added successfully!\n";
    }
}

