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
        // 1. product_stocks.quantity
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->default(0)->change();
        });

        // 2. transaction_items.quantity
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->change();
        });

        // 3. purchase_items.quantity
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->change();
        });

        // 4. stock_movements (quantity, quantity_before, quantity_after)
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->change();
            $table->decimal('quantity_before', 15, 3)->change();
            $table->decimal('quantity_after', 15, 3)->change();
        });

        // 5. stock_transfer_items.quantity
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->decimal('quantity', 15, 3)->change();
        });

        // 6. products.min_stock (recommended untuk konsistensi)
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('min_stock', 15, 3)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert semua perubahan
        Schema::table('product_stocks', function (Blueprint $table) {
            $table->integer('quantity')->default(0)->change();
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->integer('quantity')->change();
            $table->integer('quantity_before')->change();
            $table->integer('quantity_after')->change();
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->integer('quantity')->change();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->integer('min_stock')->default(0)->change();
        });
    }
};
