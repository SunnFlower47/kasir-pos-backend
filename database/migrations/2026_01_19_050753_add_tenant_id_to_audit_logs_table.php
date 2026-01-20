<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add nullable tenant_id column first
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        // 2. Backfill tenant_id from users table
        DB::statement("
            UPDATE audit_logs 
            JOIN users ON audit_logs.user_id = users.id 
            SET audit_logs.tenant_id = users.tenant_id
            WHERE audit_logs.tenant_id IS NULL
        ");

        // 3. Optional: Make tenant_id non-nullable if we are sure all logs have users with tenants
        // For Audit Logs, system actions might happen without user, so keep it nullable but scoped.
        // However, usually we want strict tenancy. 
        // Let's keep it nullable but the Trait will filter where tenant_id = current_tenant or null?
        // Actually TenantScoped trait usually forces `where('tenant_id', Auth::user()->tenant_id)`.
        // If the row has null tenant_id, it won't be seen by tenant users (good).
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }
};
