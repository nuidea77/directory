<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->sentence(12),
            'status' => 'active',
        ];
    }
}
