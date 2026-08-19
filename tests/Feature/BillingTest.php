<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Order;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingTest extends TestCase
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
    }

    protected function enableByl(): void
    {
        config([
            'services.byl.token' => 'byl_test_token',
            'services.byl.project_id' => '42',
            'services.byl.webhook_secret' => 'whsec_test',
        ]);
    }

    public function test_checkout_creates_byl_checkout_with_correct_total(): void
    {
        $this->enableByl();

        Http::fake([
            'byl.mn/api/v1/projects/42/checkouts' => Http::response([
                'data' => ['id' => 777, 'status' => 'open', 'url' => 'https://byl.mn/h/checkout/777/abc'],
            ]),
        ]);

        $response = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'business',
            'extra_branches' => 2,
            'campaigns' => [[
                'type' => 'category_featured',
                'business_id' => $this->business->id,
                'category_id' => $this->category->id,
                'district' => 'Баянзүрх',
                'days' => 30,
            ]],
        ]);

        // 290,000 + 10,000 + (149,000 − 14,900 бизнес эрхийн 10%)
        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total', 290000 + 10000 + 149000 - 14900)
            ->assertJsonPath('data.invoice_url', 'https://byl.mn/h/checkout/777/abc');

        // Төлбөрийн дараа сайт руу буцаах URL-ууд илгээгдсэн байх ёстой
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/projects/42/checkouts')
            && $request->hasHeader('Authorization', 'Bearer byl_test_token')
            && str_contains($request['success_url'] ?? '', '/pay?return=success')
            && str_contains($request['cancel_url'] ?? '', '/pay?return=cancel')
            && ($request['items'][0]['price_data']['unit_amount'] ?? null) === 290000 + 10000 + 149000 - 14900);
    }

    public function test_order_marked_paid_on_return_sync_when_checkout_complete(): void
    {
        $this->enableByl();

        Http::fake([
            'byl.mn/api/v1/projects/42/checkouts/777' => Http::response(['data' => ['id' => 777, 'status' => 'complete', 'url' => 'https://byl.mn/x']]),
            'byl.mn/api/v1/projects/42/checkouts' => Http::response(['data' => ['id' => 777, 'status' => 'open', 'url' => 'https://byl.mn/x']]),
        ]);

        $order = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard',
        ])->json('data');

        // byl.mn-ээс буцаж ирээд төлөв асуухад (poll) шууд paid болно
        $this->actingAs($this->owner)->getJson("/api/v1/orders/{$order['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->assertSame('standard', $this->organization->refresh()->plan);
    }

    public function test_webhook_activates_plan_and_campaign(): void
    {
        $this->enableByl();

        Http::fake([
            'byl.mn/api/v1/projects/42/checkouts' => Http::response(['data' => ['id' => 777, 'url' => 'https://byl.mn/x', 'status' => 'open']]),
        ]);

        $order = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'business',
            'campaigns' => [[
                'type' => 'category_featured',
                'business_id' => $this->business->id,
                'category_id' => $this->category->id,
                'district' => 'Баянзүрх',
                'days' => 30,
            ]],
        ])->json('data');

        // Буруу дүнтэй webhook idempotent-оор алгасагдана
        $wrongBody = json_encode(['type' => 'checkout.completed', 'data' => ['object' => ['id' => 777, 'status' => 'complete', 'amount_total' => 1000]]]);
        $this->call('POST', '/webhooks/byl', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_BYL_SIGNATURE' => hash_hmac('sha256', $wrongBody, 'whsec_test'),
        ], $wrongBody)->assertOk();
        $this->assertSame('pending', Order::find($order['id'])->status);

        $body = json_encode(['type' => 'checkout.completed', 'data' => ['object' => ['id' => 777, 'status' => 'complete', 'amount_total' => $order['total']]]]);
        $signature = hash_hmac('sha256', $body, 'whsec_test');

        $this->call('POST', '/webhooks/byl', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_BYL_SIGNATURE' => $signature,
        ], $body)->assertOk();

        $this->organization->refresh();
        $this->assertSame('business', $this->organization->plan);
        $this->assertTrue($this->organization->plan_expires_at->isFuture());
        $this->assertTrue($this->business->refresh()->is_verified); // бизнес эрх → баталгаажсан тэмдэг

        $campaign = Campaign::first();
        $this->assertSame('active', $campaign->status);
        $this->assertSame(1, $campaign->slot);

        // Давхар webhook — хугацаа дахин сунгагдахгүй (idempotent)
        $expiresAt = $this->organization->plan_expires_at;
        $this->call('POST', '/webhooks/byl', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_BYL_SIGNATURE' => $signature,
        ], $body)->assertOk();
        $this->assertEquals($expiresAt, $this->organization->refresh()->plan_expires_at);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $this->enableByl();

        $body = json_encode(['type' => 'checkout.completed', 'data' => ['object' => ['id' => 1]]]);

        $this->call('POST', '/webhooks/byl', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_BYL_SIGNATURE' => 'invalid',
        ], $body)->assertStatus(400);
    }

    public function test_dev_mode_checkout_paid_and_activated_immediately(): void
    {
        // byl тохиргоогүй → шууд төлөгдөнө
        $response = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard',
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'paid');
        $this->assertSame('standard', $this->organization->refresh()->plan);
    }

    public function test_branch_addon_raises_branch_limit(): void
    {
        // Үнэгүй эрх: 1 салбар (setUp-д аль хэдийн үүссэн) — хоёр дахь нь багтахгүй
        $this->actingAs($this->owner)->postJson("/api/v1/console/businesses/{$this->business->id}/branches", [
            'name' => 'Хоёр дахь', 'district' => 'Сүхбаатар', 'address' => 'X', 'phone' => '99881123',
        ])->assertStatus(422);

        // 1 нэмэлт салбар худалдаж авна (dev горим — шууд төлөгдөнө)
        $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'extra_branches' => 1,
        ])->assertCreated()->assertJsonPath('data.status', 'paid');

        $this->assertSame(1, $this->organization->refresh()->extra_branches);

        // Одоо хоёр дахь салбар багтана, гурав дахь нь багтахгүй
        $this->actingAs($this->owner)->postJson("/api/v1/console/businesses/{$this->business->id}/branches", [
            'name' => 'Хоёр дахь', 'district' => 'Сүхбаатар', 'address' => 'X', 'phone' => '99881123',
        ])->assertCreated();

        $this->actingAs($this->owner)->postJson("/api/v1/console/businesses/{$this->business->id}/branches", [
            'name' => 'Гурав дахь', 'district' => 'Баянгол', 'address' => 'Y', 'phone' => '99881124',
        ])->assertStatus(422);
    }

    public function test_category_featured_slots_fill_then_queue(): void
    {
        // 3 зай дүүргэнэ
        foreach (range(1, 3) as $i) {
            Campaign::factory()->create([
                'organization_id' => $this->organization->id,
                'business_id' => $this->business->id,
                'type' => 'category_featured',
                'category_id' => $this->category->id,
                'district' => 'Баянзүрх',
                'slot' => $i,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => now()->addDays(10),
            ]);
        }

        // Хатуу лимит: нэг ангилал+дүүрэгт ихдээ 3 зар — 4 дэхийг худалдахгүй
        $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'campaigns' => [[
                'type' => 'category_featured',
                'business_id' => $this->business->id,
                'category_id' => $this->category->id,
                'district' => 'Баянзүрх',
                'days' => 7,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors(['campaigns']);

        // Өөр дүүрэгт зай сул тул худалдаж болно
        $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'campaigns' => [[
                'type' => 'category_featured',
                'business_id' => $this->business->id,
                'category_id' => $this->category->id,
                'district' => 'Хан-Уул',
                'days' => 7,
            ]],
        ])->assertCreated();

        // Slots endpoint зөв тоолно
        $this->actingAs($this->owner)->getJson('/api/v1/slots?type=category_featured&category_id='.$this->category->id.'&district='.urlencode('Баянзүрх'))
            ->assertOk()
            ->assertJsonPath('occupied', 3)
            ->assertJsonPath('queued', 0);
    }

    public function test_queued_campaign_promoted_when_slot_frees(): void
    {
        $active = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'business_id' => $this->business->id,
            'type' => 'category_featured',
            'category_id' => $this->category->id,
            'district' => 'Баянзүрх',
            'slot' => 1,
            'status' => 'active',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subMinute(), // дууссан
        ]);

        $queued = Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'business_id' => $this->business->id,
            'type' => 'category_featured',
            'category_id' => $this->category->id,
            'district' => 'Баянзүрх',
            'status' => 'queued',
            'days' => 30,
        ]);

        app(\App\Services\Billing\CampaignService::class)->syncNow();

        $this->assertSame('expired', $active->refresh()->status);
        $this->assertSame('active', $queued->refresh()->status);
        $this->assertNotNull($queued->ends_at);
    }

    public function test_pending_order_can_be_canceled(): void
    {
        $this->enableByl();

        Http::fake([
            'byl.mn/api/v1/projects/42/checkouts/777' => Http::response(['data' => ['id' => 777, 'status' => 'open']]),
            'byl.mn/api/v1/projects/42/checkouts' => Http::response(['data' => ['id' => 777, 'url' => 'https://byl.mn/x', 'status' => 'open']]),
        ]);

        $order = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'campaigns' => [[
                'type' => 'category_featured',
                'business_id' => $this->business->id,
                'category_id' => $this->category->id,
                'district' => 'Баянзүрх',
                'days' => 7,
            ]],
        ])->json('data');

        $this->actingAs($this->owner)->deleteJson("/api/v1/orders/{$order['id']}")
            ->assertOk()
            ->assertJsonPath('data.status', 'void');

        $this->assertSame('canceled', Campaign::first()->status);

        // Төлөгдсөн захиалгыг цуцлах боломжгүй
        $paid = Order::factory()->create(['user_id' => $this->owner->id, 'status' => 'paid']);
        $this->actingAs($this->owner)->deleteJson("/api/v1/orders/{$paid->id}")->assertStatus(422);
    }

    public function test_stale_pending_orders_expire_via_command(): void
    {
        $order = Order::factory()->create([
            'user_id' => $this->owner->id,
            'organization_id' => $this->organization->id,
            'status' => 'pending',
            'created_at' => now()->subDays(2),
        ]);
        $fresh = Order::factory()->create([
            'user_id' => $this->owner->id,
            'organization_id' => $this->organization->id,
            'status' => 'pending',
        ]);
        Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'business_id' => $this->business->id,
            'order_id' => $order->id,
            'status' => 'pending_payment',
        ]);

        $this->artisan('orders:expire')->assertSuccessful();

        $this->assertSame('expired', $order->refresh()->status);
        $this->assertSame('pending', $fresh->refresh()->status);
        $this->assertSame('canceled', Campaign::first()->status);
    }

    public function test_expired_plan_loses_verified_badge(): void
    {
        $this->organization->update(['plan' => 'business', 'plan_expires_at' => now()->subDay()]);
        $this->business->update(['is_verified' => true]);

        $this->artisan('plans:sync')->assertSuccessful();

        $this->assertFalse($this->business->refresh()->is_verified);

        // Идэвхтэй эрхтэй байгууллагад нөлөөлөхгүй
        $this->organization->update(['plan_expires_at' => now()->addYear()]);
        $this->business->update(['is_verified' => true]);
        $this->artisan('plans:sync')->assertSuccessful();
        $this->assertTrue($this->business->refresh()->is_verified);
    }

    public function test_admin_can_edit_plan_price_and_it_applies_immediately(): void
    {
        $this->seed(\Database\Seeders\PlanSeeder::class);
        $admin = User::factory()->create(['is_admin' => true]);

        $plan = \App\Models\Plan::where('key', 'standard')->first();

        $this->actingAs($admin)->putJson("/api/v1/admin/plans/{$plan->id}", [
            'name' => 'Стандарт',
            'price' => 150000,
            'term_years' => 1,
            'limits' => ['businesses' => 2, 'branches' => 0, 'images_per_branch' => 5],
            'analytics' => true,
        ])->assertOk();

        // Production дээр request бүр boot хийхдээ DB-ээс уншина;
        // нэг процессын тестэд override-ийг гараар сэргээнэ
        config(['billing.plans' => \App\Models\Plan::asConfig()]);

        $order = $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard',
        ]);

        $order->assertCreated()->assertJsonPath('data.total', 150000);
    }

    public function test_admin_campaigns_list_shows_active_ads_with_end_dates(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // 3 зай дүүрэн (5 хоногийн дараа дуусна) + 1 дараалалд — sync идэвхжүүлж чадахгүй
        foreach (range(1, 3) as $i) {
            Campaign::factory()->create([
                'organization_id' => $this->organization->id,
                'business_id' => $this->business->id,
                'category_id' => $this->category->id,
                'district' => 'Баянзүрх',
                'status' => 'active',
                'slot' => $i,
                'starts_at' => now()->subDays(2),
                'ends_at' => now()->addDays(5),
            ]);
        }
        Campaign::factory()->create([
            'organization_id' => $this->organization->id,
            'business_id' => $this->business->id,
            'category_id' => $this->category->id,
            'district' => 'Баянзүрх',
            'status' => 'queued',
        ]);

        $res = $this->actingAs($admin)->getJson('/api/v1/admin/campaigns');
        $res->assertOk()
            ->assertJsonPath('kpis.active', 3)
            ->assertJsonPath('kpis.queued', 1)
            ->assertJsonPath('kpis.expiring_7d', 3);

        $active = collect($res->json('data'))->firstWhere('status', 'active');
        $this->assertNotNull($active['ends_at']);
        $this->assertSame(5, $active['days_left']);
    }

    public function test_only_owner_can_checkout_for_organization(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard',
        ])->assertForbidden();
    }
}
