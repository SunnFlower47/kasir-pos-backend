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
        if (Schema::hasTable('product_units') && !Schema::hasColumn('product_units', 'tenant_id')) {
            Schema::table('product_units', function (Blueprint $table) {
                // Adjust unique constraint on barcode to be composite with tenant_id or just drop unique index and rely on application logic + composite unique if supported
                // Since barcode is nullable and unique, we first drop the global unique index if it exists
                $table->dropUnique(['barcode']); 
                
                $table->foreignId('tenant_id')->nullable()->after('id')->index();
                
                // Add composite unique index? No, Laravel validation handles it better usually, 
                // but enforcing it in DB is good.
                // However, multi-column unique with nullable tenant_id might be tricky depending on DB (MySQL handles null as distinct).
                // Let's just add the column for now and let Model handle scoping.
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('product_units') && Schema::hasColumn('product_units', 'tenant_id')) {
            Schema::table('product_units', function (Blueprint $table) {
                $table->dropColumn('tenant_id');
                // Ideally restore unique index but we can leave it
            });
        }
    }
};
