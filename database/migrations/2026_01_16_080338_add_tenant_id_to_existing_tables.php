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
        $tables = [
            'users',
            'outlets',
            'products',
            'categories',
            'units',
            'customers',
            'suppliers',
            'transactions',
            'purchases',
            'expenses',
            'product_stocks',
            'stock_movements',
            'stock_transfers',
            'promotions'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    // Add tenant_id as nullable first for existing data
                    if (!Schema::hasColumn($table->getTable(), 'tenant_id')) {
                        $table->foreignId('tenant_id')
                              ->nullable()
                              ->after('id')
                              ->constrained('tenants')
                              ->onDelete('cascade');
                        
                        $table->index('tenant_id');
                    }
                });
            }
        }

        // Update Unique Constraints to be Per-Tenant
        // Products: SKU and Barcode
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                // Drop old unique indexes if they exist
                // Note: Index names might vary, using array syntax to let Laravel find them
                try {
                    $table->dropUnique(['sku']);
                    $table->dropUnique(['barcode']);
                } catch (\Exception $e) {
                    // Index might not exist or named differently
                }

                // Add new unique indexes scoped by tenant
                $table->unique(['tenant_id', 'sku'], 'products_tenant_sku_unique');
                $table->unique(['tenant_id', 'barcode'], 'products_tenant_barcode_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'users',
            'outlets',
            'products',
            'categories',
            'units',
            'customers',
            'suppliers',
            'transactions',
            'purchases',
            'expenses',
            'product_stocks',
            'stock_movements',
            'stock_transfers',
            'promotions'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (Schema::hasColumn($table->getTable(), 'tenant_id')) {
                        $table->dropForeign(['tenant_id']);
                        $table->dropColumn('tenant_id');
                    }
                });
            }
        }
    }
};
