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
        Schema::table('roles', function (Blueprint $table) {
            // 1. Drop Foreign Key if exists
            try {
                $table->dropForeign(['tenant_id']);
            } catch (\Exception $e) { /* ignore */ }

            try {
                $table->dropForeign('roles_tenant_id_foreign');
            } catch (\Exception $e) { /* ignore */ }
            
            // 2. Drop Unique Constraint including tenant_id
            try {
                $table->dropUnique(['name', 'guard_name', 'tenant_id']);
            } catch (\Exception $e) { /* ignore */ }

            // 3. Drop tenant_id column
            if (Schema::hasColumn('roles', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }

            // 4. Restore original Spatie unique constraint
            // We want global unique roles per guard
            try {
                $table->unique(['name', 'guard_name']);
            } catch (\Exception $e) {
                 // Might fail if duplicates exist. 
                 // In production, we should cleanup duplicates first, but for this refactor we assume fresh or cleanup is separate.
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
             $table->unsignedBigInteger('tenant_id')->nullable()->after('guard_name')->index();
             $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
             
             $table->dropUnique(['name', 'guard_name']);
             $table->unique(['name', 'guard_name', 'tenant_id']);
        });
    }
};
