<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AssignSanctumRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get users and assign sanctum roles
        $superAdminUser = User::where('email', 'superadmin@kasirpos.com')->first();
        if ($superAdminUser) {
            $superAdminRole = Role::where('name', 'Super Admin')->where('guard_name', 'sanctum')->first();
            if ($superAdminRole && !$superAdminUser->hasRole($superAdminRole)) {
                $superAdminUser->assignRole($superAdminRole);
                echo "Assigned Super Admin role (sanctum) to {$superAdminUser->name}\n";
            }
        }

        $adminUser = User::where('email', 'admin@kasirpos.com')->first();
        if ($adminUser) {
            $adminRole = Role::where('name', 'Admin')->where('guard_name', 'sanctum')->first();
            if ($adminRole && !$adminUser->hasRole($adminRole)) {
                $adminUser->assignRole($adminRole);
                echo "Assigned Admin role (sanctum) to {$adminUser->name}\n";
            }
        }

        $managerUser = User::where('email', 'manager@kasirpos.com')->first();
        if ($managerUser) {
            $managerRole = Role::where('name', 'Manager')->where('guard_name', 'sanctum')->first();
            if ($managerRole && !$managerUser->hasRole($managerRole)) {
                $managerUser->assignRole($managerRole);
                echo "Assigned Manager role (sanctum) to {$managerUser->name}\n";
            }
        }

        $cashierUser = User::where('email', 'cashier@kasirpos.com')->first();
        if ($cashierUser) {
            $cashierRole = Role::where('name', 'Cashier')->where('guard_name', 'sanctum')->first();
            if ($cashierRole && !$cashierUser->hasRole($cashierRole)) {
                $cashierUser->assignRole($cashierRole);
                echo "Assigned Cashier role (sanctum) to {$cashierUser->name}\n";
            }
        }

        echo "Sanctum roles assigned successfully!\n";
    }
}
