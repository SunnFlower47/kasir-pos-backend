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
        // 1. Tenants Table
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('owner_name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Subscriptions Table
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('plan_name')->nullable(); // basic, pro, enterprise
            $table->string('status')->default('pending'); // pending, active, expired, cancelled
            $table->decimal('price', 15, 2)->nullable();
            $table->string('period')->nullable(); // monthly, yearly
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('next_billing_date')->nullable();
            $table->json('features')->nullable();
            $table->integer('max_outlets')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        // 3. Subscription Payments Table
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->string('order_id')->unique(); // From Midtrans
            $table->string('payment_method')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending'); // pending, paid, expired, failed
            $table->string('transaction_reference')->nullable();
            $table->json('midtrans_response')->nullable();
            $table->timestamp('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('tenants');
    }
};
