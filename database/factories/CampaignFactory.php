<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\Campaign;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'business_id' => Business::factory(),
            'type' => 'category_featured',
            'days' => 30,
            'price' => 149000,
            'status' => 'pending_payment',
        ];
    }
}
