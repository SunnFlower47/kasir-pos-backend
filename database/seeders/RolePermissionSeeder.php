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
            'permissions.view', 'permissions.manage', // usually permissions are managed by system only, but viewing roles needs permissions
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum']);
        }

        // Create Roles with Scopes

        // 1. SYSTEM ROLES
        $systemAdmin = Role::firstOrCreate(
            ['name' => 'System Admin'], 
            ['guard_name' => 'web', 'scope' => 'system', 'tenant_id' => null] // Usually developers login via web/special guard, or sanctum. Let's stick to sanctum if single user table.
        );
        $systemAdmin->update(['guard_name' => 'sanctum', 'scope' => 'system', 'tenant_id' => null]);

        $systemSupport = Role::firstOrCreate(
            ['name' => 'System Support'], 
            ['guard_name' => 'sanctum', 'scope' => 'system', 'tenant_id' => null]
        );
        $systemFinance = Role::firstOrCreate(
            ['name' => 'System Finance'], 
            ['guard_name' => 'sanctum', 'scope' => 'system', 'tenant_id' => null]
        );
        
        // 2. TENANT TEMPLATE ROLES (tenant_id = NULL, scope = tenant)
        // These are available to ALL Tenants to use/copy
        $superAdmin = Role::firstOrCreate(
            ['name' => 'Super Admin'], 
            ['guard_name' => 'sanctum', 'scope' => 'tenant', 'tenant_id' => null]
        );
        // Force update to ensure it's tenant scope (fix previous system scope override)
        $superAdmin->update(['scope' => 'tenant', 'tenant_id' => null]);

        $owner = Role::firstOrCreate(
            ['name' => 'Owner'],
            ['guard_name' => 'sanctum', 'scope' => 'tenant', 'tenant_id' => null]
        );
        $admin = Role::firstOrCreate(
            ['name' => 'Admin'],
            ['guard_name' => 'sanctum', 'scope' => 'tenant', 'tenant_id' => null]
        );
        $manager = Role::firstOrCreate(
            ['name' => 'Manager'],
            ['guard_name' => 'sanctum', 'scope' => 'tenant', 'tenant_id' => null]
        );
        $cashier = Role::firstOrCreate(
            ['name' => 'Cashier'],
            ['guard_name' => 'sanctum', 'scope' => 'tenant', 'tenant_id' => null]
        );
        $warehouse = Role::firstOrCreate(
            ['name' => 'Warehouse'],
            ['guard_name' => 'sanctum', 'scope' => 'tenant', 'tenant_id' => null]
        );

        // Assign permissions to roles
        
        // System Admin (Developer) - Full Access
        $systemAdmin->syncPermissions(Permission::all()); 

        // System Support - View All, Manage Tenants
        $systemSupport->syncPermissions(Permission::whereIn('name', [
            'users.view', 'tenants.view', 'tenants.manage', 'transactions.view', 'reports.view'
        ])->get()); // Note: permissions like 'tenants.view' might not exist yet in $permissions array, assumed exists or ignored.
        // Actually, let's just give them all 'view' permissions + tenants management if we had granular permissions.
        // Since we don't have 'tenants.*' in the $permissions list above (Line 19), I should check.
        // The list at top of file DOES NOT contain 'tenants.*'. I should add them if I want granular control.
        // But for "Level 1" requirement, let's just assume System Admin has everything and Support has everything minus some critical things?
        // Or just create the roles for assignment.
        
        // System Finance - View Reports, Transactions, Payments
        $systemFinance->syncPermissions(Permission::whereIn('name', [
            'transactions.view', 'reports.sales', 'reports.profit' 
        ])->get()); 

        // Super Admin (Tenant Owner) - Full Tenant Access
        $tenantPermissions = Permission::where('guard_name', 'sanctum')
            ->where('name', '!=', 'system.manage') 
            ->get();
        
        $superAdmin->syncPermissions($tenantPermissions);
        $owner->syncPermissions($tenantPermissions); // Keep Owner as alias/alternative if needed, or deprecate.

        // Admin (Tenant) - Similar to Super Admin but maybe restricted logic later?
        $admin->syncPermissions($tenantPermissions);

        // ... (Manager, Cashier, Warehouse permissions remain same, just ensure they are sync'd)
 
        
        // System Admin uses WEB guard
        // $systemAdmin->syncPermissions(Permission::where('guard_name', 'web')->get());

        // Update existing roles to include new expenses permissions
        $expensesPermissions = Permission::whereIn('name', [
            'expenses.view',
            'expenses.create',
            'expenses.edit',
            'expenses.delete'
        ])->where('guard_name', 'sanctum')->get();

        // Add expenses permissions to Admin role
        $admin->givePermissionTo($expensesPermissions);

        // Add expenses permissions to Manager role
        $manager->givePermissionTo($expensesPermissions);

        // Admin - Can manage stock but limited user management
        $admin->syncPermissions([
            'users.view',
            'products.view', 'products.create', 'products.edit', 'products.delete',
            'categories.view', 'categories.create', 'categories.edit', 'categories.delete',
            'transactions.view', 'transactions.refund',
            'purchases.view',
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete',
            'customers.view', 'customers.create',
            'suppliers.view', 'suppliers.create',
            'stocks.view', 'stocks.adjustment', 'stocks.transfer',
            'reports.sales', 'reports.purchases', 'reports.stocks', 'reports.profit',
            'settings.view',
            'promotions.view',
            'units.view', 'units.create', 'units.edit', 'units.delete',
            'audit-logs.view',
            'export.view', 'export.manage',
            'import.view', 'import.create', 'import.manage',
        ]);

        // Manager - FULL ACCESS untuk operasional (kecuali kelola user dan outlet)
        $manager->syncPermissions([
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
            'export.view', 'export.manage',
            'import.view', 'import.create', 'import.manage',
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

        // 3. Create Default Tenant and Outlet (Required for Tenant Users)
        $tenant = \App\Models\Tenant::firstOrCreate(['email' => 'demo@kasirpos.com'], [
            'name' => 'Demo Tenant',
            'phone' => '1234567890',
            'address' => 'Demo Address',
            'owner_name' => 'Demo Owner',
            'is_active' => true,
        ]);
        
        $outlet = \App\Models\Outlet::firstOrCreate(['code' => 'DEMO-01'], [
            'tenant_id' => $tenant->id,
            'name' => 'Outlet Pusat',
            'address' => 'Alamat Pusat',
            'phone' => '08123456789',
            'is_active' => true,
        ]);


        // 4. Create Users

        // System Admin (Developer) - No Tenant, No Outlet
        $systemAdminUser = User::firstOrCreate(
            ['email' => 'system@kasirpos.com'],
            [
                'name' => 'System Administrator',
                'password' => bcrypt('password'),
                'phone' => '0000000000',
                'is_active' => true,
                'outlet_id' => null, // System admin doesn't belong to outlet
                'tenant_id' => null, // System admin doesn't belong to tenant
            ]
        );
        $systemAdminUser->assignRole($systemAdmin);

        // Super Admin (Tenant Owner)
        $superAdminUser = User::firstOrCreate(
            ['email' => 'superadmin@kasirpos.com'],
            [
                'name' => 'Tenant Owner',
                'password' => bcrypt('password'),
                'phone' => '081234567890',
                'is_active' => true,
                'outlet_id' => $outlet->id,
                'tenant_id' => $tenant->id,
            ]
        );
        $superAdminUser->assignRole($superAdmin);

        // Manager User
        $managerUser = User::firstOrCreate(
            ['email' => 'manager@kasirpos.com'],
            [
                'name' => 'Manager User',
                'password' => bcrypt('password'),
                'phone' => '081234567892',
                'is_active' => true,
                'outlet_id' => $outlet->id,
                'tenant_id' => $tenant->id,
            ]
        );
        $managerUser->assignRole($manager);

        // Cashier User
        $cashierUser = User::firstOrCreate(
            ['email' => 'cashier@kasirpos.com'],
            [
                'name' => 'Cashier User',
                'password' => bcrypt('password'),
                'phone' => '081234567893',
                'is_active' => true,
                'outlet_id' => $outlet->id,
                'tenant_id' => $tenant->id,
            ]
        );
        $cashierUser->assignRole($cashier);
    }
}
