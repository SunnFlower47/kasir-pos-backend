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
        // 1. Create Permissions
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

            // Expense Management
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete',

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

            // Unit Management
            'units.view', 'units.create', 'units.edit', 'units.delete',

            // Export/Import
            'export.view', 'export.manage',
            'import.view', 'import.create', 'import.manage',

            // Role & Permission Management
            'roles.view', 'roles.create', 'roles.edit', 'roles.delete',
            'permissions.view', 'permissions.manage', 
            
            // Tenant Management (System Only)
            'tenants.view', 'tenants.manage', 'system.manage'
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // 2. Create Roles (GLOBAL TEMPLATES)
        
        // SYSTEM ROLES
        $systemAdmin = Role::firstOrCreate(
            ['name' => 'System Admin', 'guard_name' => 'sanctum'],
            ['scope' => 'system', 'description' => 'Super User with full access to everything']
        );

        $systemSupport = Role::firstOrCreate(
            ['name' => 'System Support', 'guard_name' => 'sanctum'],
            ['scope' => 'system', 'description' => 'Support staff with access to manage tenants']
        );
        $systemFinance = Role::firstOrCreate(
            ['name' => 'System Finance', 'guard_name' => 'sanctum'],
            ['scope' => 'system', 'description' => 'Finance staff with access to system reports']
        );
        
        // TENANT ROLES (Available to all tenants)
        $owner = Role::firstOrCreate(
            ['name' => 'Owner', 'guard_name' => 'sanctum'],
            ['scope' => 'tenant', 'description' => 'Business Owner - Full Access to Tenant Data']
        );
        // Alias Super Admin to Owner logic if needed, or deprecate. 
        // We will just create Owner as the main one.

        $admin = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'sanctum'],
            ['scope' => 'tenant', 'description' => 'Administrator - Manages operations but limited sensitive settings']
        );

        $manager = Role::firstOrCreate(
            ['name' => 'Manager', 'guard_name' => 'sanctum'],
            ['scope' => 'tenant', 'description' => 'Store Manager - Manages day-to-day operations']
        );

        $cashier = Role::firstOrCreate(
            ['name' => 'Cashier', 'guard_name' => 'sanctum'],
            ['scope' => 'tenant', 'description' => 'Cashier - Access to ensure sales and customers']
        );

        $warehouse = Role::firstOrCreate(
            ['name' => 'Warehouse', 'guard_name' => 'sanctum'],
            ['scope' => 'tenant', 'description' => 'Warehouse Staff - Manages stock and procurement']
        );

        // 3. Assign Permissions
        
        // System Admin
        $systemAdmin->syncPermissions(Permission::all());
        
        // System Support
        $systemSupport->syncPermissions(Permission::whereIn('name', [
            'users.view', 'tenants.view', 'tenants.manage', 'transactions.view', 'reports.view', 'audit-logs.view'
        ])->get());

        // TENANT PERMISSIONS (Exclude system-only perms + Global Role Management)
        $tenantPermissionsKey = Permission::where('guard_name', 'sanctum')
            ->whereNotIn('name', [
                'tenants.view', 'tenants.manage', 'system.manage',
                'roles.create', 'roles.edit', 'roles.delete',
                'permissions.manage'
            ])
            ->get();

        // Owner: Full Tenant Access
        $owner->syncPermissions($tenantPermissionsKey);

        // Admin: Almost full, maybe restrict deleting audit logs or similar?
        $admin->syncPermissions($tenantPermissionsKey);

        // Manager: Operations Focus
        $manager->syncPermissions(Permission::whereIn('name', [
             'users.view',
             'products.view', 'products.create', 'products.edit', 'products.delete',
             'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
             'transactions.view', 'transactions.create', 'transactions.edit', 'transactions.delete', 'transactions.refund',
             'purchases.view', 'purchases.create', 'purchases.edit', 'purchases.delete',
             'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete',
             'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
             'suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete',
             'stocks.view', 'stocks.adjustment', 'stocks.transfer',
             'reports.sales', 'reports.purchases', 'reports.stocks', 'reports.profit',
             'promotions.view', 'promotions.create', 'promotions.edit', 'promotions.delete',
             'outlets.view',
             'units.view', 'units.create', 'units.edit', 'units.delete',
             'export.view', 'export.manage',
             'import.view', 'import.create', 'import.manage',
        ])->get());

        // Cashier: Sales Focus
        $cashier->syncPermissions(Permission::whereIn('name', [
            'products.view',
            'categories.view',
            'transactions.view', 'transactions.create',
            'customers.view', 'customers.create',
            'stocks.view',
            'promotions.view',
        ])->get());

        // Warehouse: Stock Focus
        $warehouse->syncPermissions(Permission::whereIn('name', [
            'products.view', 'categories.view',
            'purchases.view', 'purchases.create', 'purchases.edit',
            'suppliers.view', 'suppliers.create', 'suppliers.edit',
            'stocks.view', 'stocks.adjustment', 'stocks.transfer',
            'units.view',
        ])->get());
        
        
        // 4. Seeding Demo Tenant is optional here as it might conflict with fresh refactor,
        // but let's keep it simple or remove creating users which might fail due to role changes.
        // I will skip creating demo users in this refactored seeder to rely on fresh registration or manual seed.
    }
}
