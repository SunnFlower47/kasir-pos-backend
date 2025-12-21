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
        // NOTE: For NEW databases, indexes are already in create table migrations
        // This migration is primarily for EXISTING databases that need indexes added
        // For new databases, you can skip this migration or it will safely handle duplicates

        try {
            Schema::table('transactions', function (Blueprint $table) {
                // Add indexes with try-catch per index to handle if already exists
                try {
                    $table->index('transaction_date');
                } catch (\Exception $e) {
                    // Index might already exist, continue
                }
                try {
                    $table->index('outlet_id');
                } catch (\Exception $e) {
                    // Index might already exist, continue
                }
                try {
                    $table->index(['outlet_id', 'transaction_date']);
                } catch (\Exception $e) {
                    // Index might already exist, continue
                }
                try {
                    $table->index('status');
                } catch (\Exception $e) {
                    // Index might already exist, continue
                }
                try {
                    $table->index('payment_method');
                } catch (\Exception $e) {
                    // Index might already exist, continue
                }
            });
        } catch (\Exception $e) {
            // Table might not exist or indexes already exist, continue
        }

        try {
            Schema::table('transaction_items', function (Blueprint $table) {
                try {
                    $table->index('product_id');
                } catch (\Exception $e) {
                    // Index might already exist, continue
                }
                try {
                    $table->index(['transaction_id', 'product_id']);
                } catch (\Exception $e) {
                    // Index might already exist, continue
                }
            });
        } catch (\Exception $e) {
            // Table might not exist or indexes already exist, continue
        }

        try {
            Schema::table('product_stocks', function (Blueprint $table) {
                try {
                    $table->index('product_id');
                } catch (\Exception $e) {
                    // Index might already exist, continue
                }
                try {
                    $table->index('outlet_id');
                } catch (\Exception $e) {
                    // Index might already exist, continue
                }
            });
        } catch (\Exception $e) {
            // Table might not exist or indexes already exist, continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transactions_transaction_date');
            $table->dropIndex('idx_transactions_outlet_id');
            $table->dropIndex('idx_transactions_outlet_date');
            $table->dropIndex('idx_transactions_status');
            $table->dropIndex('idx_transactions_payment_method');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropIndex('idx_transaction_items_product_id');
            $table->dropIndex('idx_transaction_items_transaction_product');
        });

        Schema::table('product_stocks', function (Blueprint $table) {
            $table->dropIndex('idx_product_stocks_product_id');
            $table->dropIndex('idx_product_stocks_outlet_id');
        });
    }
};
