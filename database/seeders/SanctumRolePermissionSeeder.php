<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SanctumRolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Permissions for Sanctum guard
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

            // Audit Logs
            'audit-logs.view', 'audit-logs.delete',

            // Export/Import
            'export.view', 'import.create',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum'
            ]);
        }

        // Create Roles for Sanctum guard
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'sanctum']);
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'sanctum']);
        $manager = Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'sanctum']);
        $cashier = Role::firstOrCreate(['name' => 'Cashier', 'guard_name' => 'sanctum']);
        $warehouse = Role::firstOrCreate(['name' => 'Warehouse', 'guard_name' => 'sanctum']);

        // Super Admin - Full access
        $superAdmin->syncPermissions($permissions);

        // Admin - Most permissions except user delete and outlet management
        $adminPermissions = [
            'users.view', 'users.create', 'users.edit',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.delete', 'transactions.refund',
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'stocks.view', 'stocks.adjustment', 'stocks.transfer',
            'reports.view', 'reports.sales', 'reports.purchases', 'reports.stocks', 'reports.profit',
            'settings.view', 'settings.edit',
            'outlets.view',
            'promotions.view', 'promotions.create', 'promotions.edit', 'promotions.delete',
            'audit-logs.view',
            'export.view', 'import.create',
        ];
        $admin->syncPermissions($adminPermissions);

        // Manager - Operational access
        $managerPermissions = [
            'users.view',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.delete', 'transactions.refund',
            'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
            'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
            'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
            'stocks.view', 'stocks.adjustment', 'stocks.transfer',
            'reports.view', 'reports.sales', 'reports.purchases', 'reports.stocks', 'reports.profit',
            'settings.view',
            'outlets.view',
            'promotions.view', 'promotions.create', 'promotions.edit', 'promotions.delete',
            'export.view', 'import.create',
        ];
        $manager->syncPermissions($managerPermissions);

        // Cashier - Basic operations
        $cashierPermissions = [
            'products.view',
            'transactions.view', 'transactions.create',
            'customers.view', 'customers.create',
            'stocks.view',
            'reports.view', 'reports.sales',
            'settings.view',
            'outlets.view',
        ];
        $cashier->syncPermissions($cashierPermissions);

        // Warehouse - Stock and purchase management
        $warehousePermissions = [
            'products.view', 'categories.view',
            'purchases.view', 'purchases.create', 'purchases.edit',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'stocks.view', 'stocks.adjustment', 'stocks.transfer',
            'reports.view', 'reports.stocks', 'reports.purchases',
            'settings.view',
            'outlets.view',
        ];
        $warehouse->syncPermissions($warehousePermissions);

        echo "Sanctum roles and permissions created successfully!\n";
    }
}
