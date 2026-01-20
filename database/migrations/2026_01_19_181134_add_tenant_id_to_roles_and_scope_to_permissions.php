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
            if (!Schema::hasColumn('roles', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('guard_name')->index();
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            }
            
            if (!Schema::hasColumn('roles', 'description')) {
                $table->text('description')->nullable()->after('guard_name');
            }

            if (!Schema::hasColumn('roles', 'scope')) {
                $table->string('scope')->default('tenant')->after('guard_name')->comment('system, tenant');
            }

            try {
                $table->dropUnique(['name', 'guard_name']);
            } catch (\Exception $e) {
                // Ignore if not found
            }
            
            try {
                $table->unique(['name', 'guard_name', 'tenant_id']);
            } catch (\Exception $e) {
                // Ignore if already exists
            }
        });

        Schema::table('permissions', function (Blueprint $table) {
            if (!Schema::hasColumn('permissions', 'scope')) {
                $table->string('scope')->default('tenant')->after('guard_name')->comment('system, tenant, both');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'tenant_id')) {
                    
            }
        });
        
        // ... simplified down
    }
};
