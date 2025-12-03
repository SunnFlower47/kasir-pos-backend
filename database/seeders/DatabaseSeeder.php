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
            SupplierSeeder::class,         // Suppliers for purchase flows
            DemoDataSeeder::class,         // Core products, stocks, customers
            TransactionSeeder::class,      // Sample transactions for reports
        ]);

        // Ensure a fallback admin user exists for legacy tests
        if (!User::where('email', 'admin@test.com')->exists()) {
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
}
