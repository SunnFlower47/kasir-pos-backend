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
        if (!Schema::hasColumn('expenses', 'tenant_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('expense_number');
            });
        }

        // SAFETY: Backfill tenant_id from related outlet
        \Illuminate\Support\Facades\DB::statement('
            UPDATE expenses e
            JOIN outlets o ON e.outlet_id = o.id
            SET e.tenant_id = o.tenant_id
            WHERE e.tenant_id IS NULL
        ');

        // Make it required and add foreign key (ignore if FK already exists logic is hard in pure migration without raw SQL check,
        // but Laravel usually handles 'change' fine. For FK, we just try adding it.)
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable(false)->change();
            // We can't easily check for FK existence in Schema builder, so we use try/catch for the FK part or assume it's safe if we control the schema.
            // If the column existed but wasn't foreign key, this works. 
            // If FK exists, this might throw.
        });
        
        try {
            Schema::table('expenses', function (Blueprint $table) {
                 $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            });
        } catch (\Exception $e) {
            // FK likely exists
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
