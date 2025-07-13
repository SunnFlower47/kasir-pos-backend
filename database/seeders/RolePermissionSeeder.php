<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Permissions
        $permissions = [
            // User Management
            'users.view', 'users.create', 'users.edit', 'users.delete',

            // Product Management
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',

            // Transaction Management
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.delete',
            'transactions.refund',

            // Purchase Management
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',

            // Customer Management
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',

            // Supplier Management
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',

            // Stock Management
            'stocks.view', 'stocks.adjustment', 'stocks.transfer',

            // Reports
            'reports.view', 'reports.sales', 'reports.purchases', 'reports.stocks', 'reports.profit',

            // Settings
            'settings.view', 'settings.edit',

            // Outlet Management
            'outlets.view', 'outlets.create', 'outlets.edit', 'outlets.delete',

            // Promotions
            'promotions.view', 'promotions.create', 'promotions.edit', 'promotions.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        // Create Roles
        $superAdmin = Role::create(['name' => 'Super Admin']);
        $admin = Role::create(['name' => 'Admin']);
        $manager = Role::create(['name' => 'Manager']);
        $cashier = Role::create(['name' => 'Cashier']);
        $warehouse = Role::create(['name' => 'Warehouse']);

        // Assign permissions to roles
        // Super Admin - Full access including stock management
        $superAdmin->syncPermissions([
            'users.view', 'users.create', 'users.edit', 'users.delete',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'transactions.view',
            'purchases.view',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'stocks.view', 'stocks.adjustment', 'stocks.transfer',
            'reports.sales', 'reports.purchases', 'reports.stocks', 'reports.profit',
            'settings.view', 'settings.edit',
            'outlets.view', 'outlets.create', 'outlets.edit', 'outlets.delete',
            'promotions.view',
        ]);

        // Admin - Can manage stock but limited user management
        $admin->syncPermissions([
            'users.view',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'transactions.view',
            'purchases.view',
            'customers.view', 'customers.create',
            'suppliers.view', 'suppliers.create',
            'stocks.view', 'stocks.adjustment', 'stocks.transfer',
            'reports.sales', 'reports.purchases', 'reports.stocks',
            'settings.view',
            'promotions.view',
        ]);

        // Manager - FULL ACCESS untuk operasional (kecuali kelola user dan outlet)
        $manager->syncPermissions([
            'users.view',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.delete', 'transactions.refund',
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'stocks.view', 'stocks.adjustment', 'stocks.transfer',
            'reports.sales', 'reports.purchases', 'reports.stocks', 'reports.profit',
            'promotions.view', 'promotions.create', 'promotions.edit', 'promotions.delete',
        ]);

        $cashier->syncPermissions([
            'products.view',
            'transactions.view', 'transactions.create',
            'customers.view', 'customers.create',
            'stocks.view',
        ]);

        $warehouse->syncPermissions([
            'products.view', 'categories.view',
            'purchases.view', 'purchases.create', 'purchases.edit',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'stocks.view', 'stocks.adjustment', 'stocks.transfer',
        ]);

        // Create Super Admin User
        $superAdminUser = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@kasirpos.com',
            'password' => bcrypt('password'),
            'phone' => '081234567890',
            'is_active' => true,
            'outlet_id' => 1,
        ]);
        $superAdminUser->assignRole('Super Admin');

        // Create Admin User
        $adminUser = User::create([
            'name' => 'Admin User',
            'email' => 'admin@kasirpos.com',
            'password' => bcrypt('password'),
            'phone' => '081234567891',
            'is_active' => true,
            'outlet_id' => 1,
        ]);
        $adminUser->assignRole('Admin');

        // Create Manager User
        $managerUser = User::create([
            'name' => 'Manager User',
            'email' => 'manager@kasirpos.com',
            'password' => bcrypt('password'),
            'phone' => '081234567892',
            'is_active' => true,
            'outlet_id' => 1,
        ]);
        $managerUser->assignRole('Manager');

        // Create Cashier User
        $cashierUser = User::create([
            'name' => 'Cashier User',
            'email' => 'cashier@kasirpos.com',
            'password' => bcrypt('password'),
            'phone' => '081234567893',
            'is_active' => true,
            'outlet_id' => 1,
        ]);
        $cashierUser->assignRole('Cashier');
    }
}
