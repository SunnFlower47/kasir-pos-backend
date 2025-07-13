<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Minimal seeders for testing - only essential data
        $this->call([
            OutletSeeder::class,           // Required: At least 1 outlet
            CategoryUnitSeeder::class,     // Required: Categories and units
            RolePermissionSeeder::class,   // Required: User roles and permissions
            SettingSeeder::class,          // Required: System settings
            // ProductSeeder::class,       // Disabled: We'll test with empty products
            // SupplierCustomerSeeder::class, // Disabled: We'll test with empty data
        ]);

        // Create admin user for testing
        $adminUser = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'outlet_id' => 1, // First outlet from OutletSeeder
            'is_active' => true,
        ]);

        // Assign Super Admin role using Spatie Permission
        $adminUser->assignRole('Super Admin');
    }
}
