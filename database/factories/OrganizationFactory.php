<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'name' => fake()->unique()->company().' ХХК',
            'registration_number' => (string) fake()->numberBetween(1000000, 9999999),
            'plan' => 'free',
        ];
    }

    public function onPlan(string $plan, int $years = 1): static
    {
        return $this->state(fn () => [
            'plan' => $plan,
            'plan_term_years' => $years,
            'plan_started_at' => now(),
            'plan_expires_at' => now()->addYears($years),
        ]);
    }
}
