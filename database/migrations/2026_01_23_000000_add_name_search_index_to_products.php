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
        Schema::table('products', function (Blueprint $table) {
            // Composite index for efficient searching by name within a tenant
            // Common query: where('tenant_id', X)->where('name', 'LIKE', '%foo%')
            // While LIKE %...% can't fully use B-Tree, the tenant_id prefix narrows scan significantly.
            // If query is 'name LIKE "Foo%"', it uses the index effectively.
            $table->index(['tenant_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'name']);
        });
    }
};
