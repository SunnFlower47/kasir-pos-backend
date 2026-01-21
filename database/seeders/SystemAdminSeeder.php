<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use App\Models\AuditLog;

class SystemAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure System Admin role exists (global scope)
        $role = Role::firstOrCreate(
            ['name' => 'System Admin', 'guard_name' => 'sanctum'],
            ['scope' => 'system', 'description' => 'Root administrator with full access to the Admin Panel.']
        );

        // Create Default System Admin User
        $user = User::firstOrCreate(
            ['email' => 'system@lumapos.com'],
            [
                'name' => 'System Administrator',
                'password' => Hash::make('password'), // Default password, change in production
                'email_verified_at' => now(),
                'tenant_id' => null, // Crucial for System Admin
                'is_active' => true,
            ]
        );

        // Assign Role
        if (!$user->hasRole('System Admin')) {
            $user->assignRole($role);
        }

        AuditLog::createLog('App\Models\User', $user->id, 'create_seeder', null, ['role' => 'System Admin']);
        
        $this->command->info('System Admin user created: admin@lumapos.com / password');
    }
}
