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
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->index(); // Email or Phone
            $table->string('code'); // Hashed code
            $table->string('type')->default('login'); // login, register, password_reset
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Index for faster lookups
            $table->index(['identifier', 'type', 'confirmed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('otp_codes');
    }
};
