<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Зээл, хэсэгчилсэн төлбөрийн аппууд (LendMN, Storepay, Pocket ...).
 */
class PaymentAppsTest extends TestCase
{
    use RefreshDatabase;

    public function test_locations_endpoint_lists_the_payment_apps(): void
    {
        $payments = $this->getJson('/api/v1/locations')->assertOk()->json('payments');

        $names = array_column($payments, 'name');

        $this->assertContains('LendMN', $names);
        $this->assertContains('Storepay', $names);
        $this->assertContains('Pocket', $names);
        $this->assertNotNull($payments[0]['slug']);
    }

    public function test_search_filters_by_payment_app(): void
    {
        Branch::factory()->create(['payments' => ['LendMN', 'Storepay']]);
        Branch::factory()->create(['payments' => ['Pocket']]);
        Branch::factory()->create(['payments' => null]);

        $this->getJson('/api/v1/search?payment=LendMN')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/search?payment=Pocket')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/search')->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_payment_apps_are_searchable_as_text(): void
    {
        $branch = Branch::factory()->create(['payments' => ['Storepay']]);
        Branch::factory()->create(['payments' => []]);

        $this->getJson('/api/v1/search?q=storepay')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $branch->id);
    }

    public function test_owner_can_save_payment_apps_on_a_branch(): void
    {
        $owner = User::factory()->create(['phone_verified_at' => now()]);
        $org = Organization::factory()->create(['owner_id' => $owner->id]);
        $business = Business::factory()->create(['organization_id' => $org->id]);
        $branch = Branch::factory()->create(['business_id' => $business->id]);

        Sanctum::actingAs($owner);

        $this->putJson("/api/v1/console/branches/{$branch->id}", [
            'payments' => ['LendMN', 'Pocket'],
        ])->assertOk();

        $this->assertSame(['LendMN', 'Pocket'], $branch->fresh()->payments);
    }

    public function test_branch_payload_exposes_payments(): void
    {
        Branch::factory()->create(['payments' => ['Ард Апп']]);

        $this->getJson('/api/v1/search')
            ->assertOk()
            ->assertJsonPath('data.0.payments', ['Ард Апп']);
    }
}
