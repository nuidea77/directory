<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => 'KH-'.now()->format('Y-m').'-'.fake()->unique()->numberBetween(1000, 9999),
            'user_id' => User::factory(),
            'total' => 149000,
            'status' => 'pending',
        ];
    }
}
