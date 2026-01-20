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
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('unit_id')->constrained('units')->onDelete('cascade');
            
            // Conversion: 1 Unit = X Base Units (e.g., 1 Box = 24 Pcs)
            // If base unit is Pcs, and we add Box, conversion_factor is 24.
            $table->decimal('conversion_factor', 10, 2);
            
            // Pricing specific to this unit (optional overrides)
            $table->decimal('purchase_price', 15, 2)->nullable();
            $table->decimal('selling_price', 15, 2)->nullable();
            $table->decimal('wholesale_price', 15, 2)->nullable();
            
            // Allow specific barcode for this unit (e.g., Box barcode different from Pcs)
            $table->string('barcode')->nullable()->unique();
            
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_units');
    }
};
