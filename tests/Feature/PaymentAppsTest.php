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

    public function test_payments_endpoint_lists_the_apps(): void
    {
        $payments = $this->getJson('/api/v1/payments')->assertOk()->json('data');

        $names = array_column($payments, 'name');

        $this->assertContains('LendMN', $names);
        $this->assertContains('Storepay', $names);
        $this->assertContains('Pocket', $names);
        $this->assertContains('Ард Апп', $names);
        $this->assertNotNull($payments[0]['slug']);
        // Лого байхгүй үед null — UI брэндийн өнгөт тэмдэг рүү шилжинэ
        $this->assertArrayHasKey('logo', $payments[0]);
        $this->assertArrayHasKey('wordmark', $payments[0]);
    }

    public function test_a_dropped_in_logo_file_is_served_with_the_app(): void
    {
        $path = public_path('img/payments/lendmn.svg');
        $existed = is_file($path);

        if (! $existed) {
            @mkdir(dirname($path), 0755, true);
            file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 8 8"></svg>');
        }

        try {
            $payments = collect($this->getJson('/api/v1/payments')->assertOk()->json('data'));
            $lend = $payments->firstWhere('slug', 'lendmn');

            $this->assertNotNull($lend['logo']);
            $this->assertStringContainsString('img/payments/lendmn.svg', $lend['logo']);
        } finally {
            if (! $existed) {
                @unlink($path);
            }
        }
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
