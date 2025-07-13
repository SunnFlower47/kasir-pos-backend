<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->numberBetween(10000, 100000);
        $discountAmount = $this->faker->numberBetween(0, $subtotal * 0.1);
        $taxAmount = $subtotal * 0.1;
        $totalAmount = $subtotal + $taxAmount - $discountAmount;
        $paidAmount = $totalAmount + $this->faker->numberBetween(0, 10000);
        $changeAmount = $paidAmount - $totalAmount;

        return [
            'transaction_number' => Transaction::generateTransactionNumber(),
            'outlet_id' => Outlet::factory(),
            'customer_id' => $this->faker->boolean(70) ? Customer::factory() : null,
            'user_id' => User::factory(),
            'transaction_date' => $this->faker->date(),
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'change_amount' => $changeAmount,
            'payment_method' => $this->faker->randomElement(['cash', 'transfer', 'qris', 'e_wallet']),
            'status' => $this->faker->randomElement(['pending', 'completed', 'cancelled', 'refunded']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Indicate that the transaction is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the transaction is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the transaction is refunded.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'refunded',
        ]);
    }
}
