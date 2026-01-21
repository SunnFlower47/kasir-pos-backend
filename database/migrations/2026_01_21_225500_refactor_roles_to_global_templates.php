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
            if (Schema::hasColumn('roles', 'tenant_id')) {
                // 1. Drop Foreign Key
                // Since we commented out the creation in the previous migration, this might not exist.
                // But if it does (from legacy), we drop it.
                // We trust Laravel to find the FK index.
                try {
                    $table->dropForeign(['tenant_id']);
                } catch (\Exception $e) {}
                
                // 2. Drop Unique Constraint
                try {
                     $table->dropUnique(['name', 'guard_name', 'tenant_id']);
                } catch (\Exception $e) {
                     try {
                        $table->dropUnique('roles_tenant_name_unique'); // as seen in error
                     } catch(\Exception $e2) {}
                }

                // 3. Drop Column
                $table->dropColumn('tenant_id');
            }
        });

        // 4. Restore original Spatie unique constraint if missing
        // Check if the unique index already exists (to avoid duplicate key error on migrate:fresh)
        $hasUniqueIndex = count(\Illuminate\Support\Facades\DB::select("SHOW INDEX FROM roles WHERE Key_name = 'roles_name_guard_name_unique'")) > 0;
        
        if (!$hasUniqueIndex) {
            Schema::table('roles', function (Blueprint $table) {
                $table->unique(['name', 'guard_name']);
            });
        }
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
