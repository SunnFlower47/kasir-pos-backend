<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
            'birth_date' => $this->faker->date(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'level' => $this->faker->randomElement(['silver', 'gold', 'platinum']),
            'loyalty_points' => $this->faker->numberBetween(0, 5000),
        ];
    }

    /**
     * Indicate that the customer is VIP (platinum level).
     */
    public function vip(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => 'platinum',
            'loyalty_points' => $this->faker->numberBetween(3000, 10000),
        ]);
    }

    /**
     * Indicate that the customer has no email.
     */
    public function withoutEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
        ]);
    }
}
