<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->paragraph(),
            'phone' => (string) fake()->numberBetween(70000000, 99999999),
            'address' => fake()->streetAddress(),
            'city' => 'Улаанбаатар',
            'district' => fake()->randomElement(['Сүхбаатар', 'Чингэлтэй', 'Баянгол', 'Баянзүрх', 'Хан-Уул', 'Сонгинохайрхан']),
            'status' => 'active',
        ];
    }

    public function featured(int $days = 7): static
    {
        return $this->state(fn () => ['featured_until' => now()->addDays($days)]);
    }
}
