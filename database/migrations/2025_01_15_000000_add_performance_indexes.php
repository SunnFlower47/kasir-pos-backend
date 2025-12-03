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
        Schema::table('transactions', function (Blueprint $table) {
            // Index for date range filtering (most common query)
            $table->index('transaction_date', 'idx_transactions_date');
            
            // Index for status filtering
            $table->index('status', 'idx_transactions_status');
            
            // Composite index for outlet + date filtering (common in reports)
            $table->index(['outlet_id', 'transaction_date'], 'idx_transactions_outlet_date');
            
            // Composite index for status + date (common in reports)
            $table->index(['status', 'transaction_date'], 'idx_transactions_status_date');
            
            // Index for transaction number search
            $table->index('transaction_number', 'idx_transactions_number');
        });

        Schema::table('products', function (Blueprint $table) {
            // Composite index for search (name, sku, barcode)
            $table->index('name', 'idx_products_name');
            $table->index('sku', 'idx_products_sku');
            $table->index('barcode', 'idx_products_barcode');
            
            // Index for active status filtering
            $table->index('is_active', 'idx_products_active');
            
            // Composite index for category + active (common filter)
            $table->index(['category_id', 'is_active'], 'idx_products_category_active');
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            // Composite index for outlet + product lookup (most common query)
            $table->index(['outlet_id', 'product_id'], 'idx_stocks_outlet_product');
            
            // Index for low stock check
            $table->index('quantity', 'idx_stocks_quantity');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            // Index for transaction lookup
            $table->index('transaction_id', 'idx_transaction_items_transaction');
            
            // Composite index for product sales analysis
            $table->index(['product_id', 'transaction_id'], 'idx_transaction_items_product_transaction');
        });

        Schema::table('customers', function (Blueprint $table) {
            // Index for search
            $table->index('name', 'idx_customers_name');
            $table->index('phone', 'idx_customers_phone');
            $table->index('email', 'idx_customers_email');
        });

        Schema::table('purchases', function (Blueprint $table) {
            // Index for date range filtering
            $table->index('purchase_date', 'idx_purchases_date');
            $table->index('status', 'idx_purchases_status');
            
            // Composite index for outlet + date
            $table->index(['outlet_id', 'purchase_date'], 'idx_purchases_outlet_date');
        });

        Schema::table('expenses', function (Blueprint $table) {
            // Index for date range filtering
            $table->index('expense_date', 'idx_expenses_date');
            
            // Composite index for outlet + date
            $table->index(['outlet_id', 'expense_date'], 'idx_expenses_outlet_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_date');
            $table->dropIndex('idx_transactions_status');
            $table->dropIndex('idx_transactions_outlet_date');
            $table->dropIndex('idx_transactions_status_date');
            $table->dropIndex('idx_transactions_number');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('idx_products_name');
            $table->dropIndex('idx_products_sku');
            $table->dropIndex('idx_products_barcode');
            $table->dropIndex('idx_products_active');
            $table->dropIndex('idx_products_category_active');
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropIndex('idx_stocks_outlet_product');
            $table->dropIndex('idx_stocks_quantity');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropIndex('idx_transaction_items_transaction');
            $table->dropIndex('idx_transaction_items_product_transaction');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('idx_customers_name');
            $table->dropIndex('idx_customers_phone');
            $table->dropIndex('idx_customers_email');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('idx_purchases_date');
            $table->dropIndex('idx_purchases_status');
            $table->dropIndex('idx_purchases_outlet_date');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex('idx_expenses_date');
            $table->dropIndex('idx_expenses_outlet_date');
        });
    }
};

