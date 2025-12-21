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
        Schema::create('shift_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outlet_id')->constrained('outlets')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('cashier_name');
            $table->string('cashier_email');
            $table->date('closing_date');
            $table->time('closing_time');
            $table->integer('total_transactions')->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->json('revenue_by_payment'); // {method: {amount, count}}
            $table->datetime('last_closing_at')->nullable(); // Waktu closing sebelumnya untuk filter transaksi
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('outlet_id');
            $table->index('user_id');
            $table->index('closing_date');
            $table->index(['outlet_id', 'closing_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_closings');
    }
};
