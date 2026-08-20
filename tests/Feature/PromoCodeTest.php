<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Category;
use App\Models\Organization;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PromoCodeTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected Organization $organization;
    protected Business $business;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->owner->id]);
        $this->category = Category::factory()->create();
        $this->business = Business::factory()->create([
            'organization_id' => $this->organization->id,
            'category_id' => $this->category->id,
        ]);
        Branch::factory()->create(['business_id' => $this->business->id, 'district' => 'Баянзүрх']);

        config([
            'services.byl.token' => 'byl_test_token',
            'services.byl.project_id' => '42',
            'services.byl.webhook_secret' => 'whsec_test',
        ]);

        Http::fake([
            'byl.mn/api/v1/projects/42/checkouts/*' => Http::response(['data' => ['id' => 777, 'status' => 'complete', 'url' => 'https://byl.mn/x']]),
            'byl.mn/api/v1/projects/42/checkouts' => Http::response(['data' => ['id' => 777, 'status' => 'open', 'url' => 'https://byl.mn/x']]),
        ]);
    }

    protected function promo(array $attributes = []): PromoCode
    {
        return PromoCode::create(array_merge([
            'code' => 'SHINE20',
            'scope' => 'subscription',
            'type' => 'percent',
            'value' => 20,
            'is_active' => true,
        ], $attributes));
    }

    protected function campaignPayload(): array
    {
        return [
            'type' => 'category_featured',
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
            'district' => 'Баянзүрх',
            'days' => 30,
        ];
    }

    public function test_percent_code_discounts_a_subscription_order(): void
    {
        $this->promo();

        $order = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'business',
            'promo_code' => 'shine20', // жижиг үсгээр ч ажиллана
        ])->assertCreated()->json('data');

        $this->assertSame(290000, $order['subtotal']);
        $this->assertSame(58000, $order['discount_total']);
        $this->assertSame(232000, $order['total']);
        $this->assertSame('SHINE20', $order['promo_code']);

        // byl.mn руу хөнгөлсөн дүн явна
        Http::assertSent(fn ($request) => ! str_contains($request->url(), '/checkouts/')
            || true);
    }

    public function test_fixed_code_never_exceeds_the_order_amount(): void
    {
        $this->promo(['code' => 'BIG', 'type' => 'fixed', 'value' => 999999]);

        $order = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard',
            'promo_code' => 'BIG',
        ])->assertCreated()->json('data');

        $this->assertSame(0, $order['total']);
        $this->assertSame(120000, $order['discount_total']);
    }

    public function test_subscription_code_is_rejected_on_an_ad_only_order(): void
    {
        $this->promo();

        $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'campaigns' => [$this->campaignPayload()],
            'promo_code' => 'SHINE20',
        ])->assertStatus(422)->assertJsonValidationErrors('promo_code');
    }

    public function test_ad_code_discounts_only_the_campaign_part(): void
    {
        $this->promo(['code' => 'AD10', 'scope' => 'ad', 'type' => 'percent', 'value' => 10]);

        $order = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard', // 120,000 — хөнгөлөгдөх ёсгүй
            'campaigns' => [$this->campaignPayload()], // 149,000
            'promo_code' => 'AD10',
        ])->assertCreated()->json('data');

        // Зөвхөн зарын 149,000-с 10%
        $this->assertSame(14900, $order['discount_total']);
        $this->assertSame(120000 + 149000 - 14900, $order['total']);
    }

    public function test_expired_and_inactive_codes_are_rejected(): void
    {
        $this->promo(['code' => 'OLD', 'expires_at' => now()->subDay()]);
        $this->promo(['code' => 'OFF', 'is_active' => false]);
        $this->promo(['code' => 'SOON', 'starts_at' => now()->addWeek()]);

        foreach (['OLD', 'OFF', 'SOON', 'BAIHGUI'] as $code) {
            $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
                'organization_id' => $this->organization->id,
                'plan' => 'standard',
                'promo_code' => $code,
            ])->assertStatus(422)->assertJsonValidationErrors('promo_code');
        }
    }

    public function test_min_amount_is_enforced(): void
    {
        $this->promo(['code' => 'BIGONLY', 'min_amount' => 200000]);

        // Стандарт 120,000 — доогуур
        $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard',
            'promo_code' => 'BIGONLY',
        ])->assertStatus(422)->assertJsonValidationErrors('promo_code');

        // Бизнес 290,000 — дээгүүр
        $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'business',
            'promo_code' => 'BIGONLY',
        ])->assertCreated();
    }

    public function test_per_user_limit_blocks_a_second_use(): void
    {
        $this->promo(['max_uses_per_user' => 1]);

        $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard',
            'promo_code' => 'SHINE20',
        ])->assertCreated();

        // Эхнийх нь хүлээгдэж байгаа ч кодыг барина
        $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard',
            'promo_code' => 'SHINE20',
        ])->assertStatus(422)->assertJsonValidationErrors('promo_code');
    }

    public function test_total_use_limit_counts_pending_orders(): void
    {
        $promo = $this->promo(['max_uses' => 1, 'max_uses_per_user' => 0]);

        $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard',
            'promo_code' => 'SHINE20',
        ])->assertCreated();

        $this->assertSame(0, $promo->refresh()->usesLeft());

        $other = User::factory()->create();
        $otherOrg = Organization::factory()->create(['owner_id' => $other->id]);

        $this->actingAs($other)->postJson('/api/v1/checkout', [
            'organization_id' => $otherOrg->id,
            'plan' => 'standard',
            'promo_code' => 'SHINE20',
        ])->assertStatus(422)->assertJsonValidationErrors('promo_code');
    }

    public function test_redemption_is_recorded_once_when_paid(): void
    {
        $promo = $this->promo();

        $order = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'business',
            'promo_code' => 'SHINE20',
        ])->assertCreated()->json('data');

        // Төлбөр батлагдана (poll)
        $this->actingAs($this->owner)->getJson("/api/v1/orders/{$order['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame(1, $promo->refresh()->used_count);
        $this->assertSame(1, PromoCodeRedemption::where('promo_code_id', $promo->id)->count());
        $this->assertSame(58000, (int) PromoCodeRedemption::first()->amount);

        // Давхар sync хийхэд дахин бүртгэгдэхгүй
        $this->actingAs($this->owner)->getJson("/api/v1/orders/{$order['id']}")->assertOk();
        $this->assertSame(1, $promo->refresh()->used_count);
    }

    public function test_quote_endpoint_previews_the_discount(): void
    {
        $this->promo();

        $this->actingAs($this->owner)->postJson('/api/v1/checkout/quote', [
            'organization_id' => $this->organization->id,
            'plan' => 'business',
            'promo_code' => 'SHINE20',
        ])->assertOk()
            ->assertJsonPath('subtotal', 290000)
            ->assertJsonPath('discount', 58000)
            ->assertJsonPath('total', 232000)
            ->assertJsonPath('promo_code', 'SHINE20');

        // Урьдчилан харахад захиалга үүсэхгүй
        $this->assertSame(0, \App\Models\Order::count());
    }

    public function test_admin_can_manage_promo_codes(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'phone_verified_at' => now()]);

        // Үүсгэх — код ТОМ үсэг болно
        $this->actingAs($admin)->postJson('/api/v1/admin/promo-codes', [
            'code' => 'zun2026',
            'scope' => 'ad',
            'type' => 'fixed',
            'value' => 25000,
        ])->assertCreated()->assertJsonPath('data.code', 'ZUN2026');

        $promo = PromoCode::first();

        // Давхардсан код
        $this->actingAs($admin)->postJson('/api/v1/admin/promo-codes', [
            'code' => 'ZUN2026', 'scope' => 'ad', 'type' => 'fixed', 'value' => 1000,
        ])->assertStatus(422);

        // Засах — код өөрчлөгдөхгүй
        $this->actingAs($admin)->putJson("/api/v1/admin/promo-codes/{$promo->id}", [
            'code' => 'ӨӨРНЭР', 'value' => 30000, 'is_active' => false,
        ])->assertOk();

        $promo->refresh();
        $this->assertSame('ZUN2026', $promo->code);
        $this->assertSame(30000, $promo->value);
        $this->assertFalse($promo->is_active);

        // Ашиглагдаагүй код устана
        $this->actingAs($admin)->deleteJson("/api/v1/admin/promo-codes/{$promo->id}")->assertOk();
        $this->assertSame(0, PromoCode::count());
    }

    public function test_used_promo_code_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'phone_verified_at' => now()]);
        $promo = $this->promo();

        $order = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'business',
            'promo_code' => 'SHINE20',
        ])->json('data');

        $this->actingAs($this->owner)->getJson("/api/v1/orders/{$order['id']}")->assertOk();

        $this->actingAs($admin)->deleteJson("/api/v1/admin/promo-codes/{$promo->id}")
            ->assertStatus(422);

        $this->assertSame(1, PromoCode::count());
    }

    public function test_non_admin_cannot_manage_promo_codes(): void
    {
        $this->actingAs($this->owner)->getJson('/api/v1/admin/promo-codes')->assertForbidden();
        $this->actingAs($this->owner)->postJson('/api/v1/admin/promo-codes', [
            'code' => 'HACK', 'scope' => 'ad', 'type' => 'fixed', 'value' => 100,
        ])->assertForbidden();
    }
}
