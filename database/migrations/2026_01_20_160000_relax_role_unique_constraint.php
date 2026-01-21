<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
             return;
        }

        // Only modify constraints if tenant_id exists (Legacy Tenant logic)
        // Since we refactored to global roles, this column might not exist anymore.
        if (Schema::hasColumn($tableNames['roles'], 'tenant_id')) {

            // Safely drop old index if exists
            $existsOld = count(Illuminate\Support\Facades\DB::select("SHOW INDEX FROM {$tableNames['roles']} WHERE Key_name = 'roles_name_guard_name_unique'")) > 0;
            
            if ($existsOld) {
                Schema::table($tableNames['roles'], function (Blueprint $table) {
                    $table->dropUnique('roles_name_guard_name_unique');
                });
            }

            // Safely add new index if NOT exists
            $existsNew = count(Illuminate\Support\Facades\DB::select("SHOW INDEX FROM {$tableNames['roles']} WHERE Key_name = 'roles_tenant_name_unique'")) > 0;

            if (!$existsNew) {
                Schema::table($tableNames['roles'], function (Blueprint $table) {
                     $table->unique(['tenant_id', 'name', 'guard_name'], 'roles_tenant_name_unique');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropUnique('roles_tenant_name_unique');
            $table->unique(['name', 'guard_name']);
        });
    }
};
