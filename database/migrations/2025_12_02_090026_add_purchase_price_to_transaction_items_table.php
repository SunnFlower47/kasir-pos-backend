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
        Schema::table('transaction_items', function (Blueprint $table) {
            // Add purchase_price column after unit_price to store snapshot of purchase price at transaction time
            $table->decimal('purchase_price', 15, 2)->nullable()->after('unit_price');
        });

        // Backfill existing records with current product purchase_price
        // This ensures existing transaction items have purchase_price data
        DB::statement("
            UPDATE transaction_items 
            SET purchase_price = (
                SELECT products.purchase_price 
                FROM products 
                WHERE products.id = transaction_items.product_id
            )
            WHERE purchase_price IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn('purchase_price');
        });
    }
};
