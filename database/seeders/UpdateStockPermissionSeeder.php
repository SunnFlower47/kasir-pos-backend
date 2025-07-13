<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UpdateStockPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get existing roles
        $superAdmin = Role::findByName('Super Admin');
        $admin = Role::findByName('Admin');
        $manager = Role::findByName('Manager');

        // Add stock permissions to Super Admin
        $superAdmin->givePermissionTo(['stocks.adjustment', 'stocks.transfer']);
        
        // Add stock permissions to Admin
        $admin->givePermissionTo(['stocks.adjustment', 'stocks.transfer']);
        
        // Manager already has these permissions, but let's make sure
        $manager->givePermissionTo(['stocks.adjustment', 'stocks.transfer']);

        echo "Stock permissions updated successfully!\n";
        echo "Super Admin, Admin, and Manager now have stocks.adjustment and stocks.transfer permissions.\n";
    }
}
