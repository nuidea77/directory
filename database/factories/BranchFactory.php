<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    public function definition(): array
    {
        $district = fake()->randomElement(['Сүхбаатар', 'Чингэлтэй', 'Баянгол', 'Баянзүрх', 'Хан-Уул', 'Сонгинохайрхан']);

        return [
            'business_id' => Business::factory(),
            'name' => $district.' салбар',
            'slug' => Str::lower(Str::random(10)),
            'is_main' => false,
            'city' => 'Улаанбаатар',
            'district' => $district,
            'khoroo' => fake()->numberBetween(1, 20).'-р хороо',
            'address' => fake()->streetAddress(),
            'lat' => fake()->randomFloat(6, 47.85, 47.95),
            'lng' => fake()->randomFloat(6, 106.80, 107.05),
            'phone' => (string) fake()->numberBetween(70000000, 99999999),
            'hours' => collect(Branch::WEEKDAYS)->mapWithKeys(fn ($d) => [
                $d => $d === 'sun' ? ['closed' => true] : ['from' => '09:00', 'to' => '19:00'],
            ])->all(),
            'amenities' => fake()->randomElements(['Зогсоол', 'Картаар', 'Wi-Fi', 'Хүргэлт', 'Захиалга'], 3),
            'status' => 'active',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }
}
