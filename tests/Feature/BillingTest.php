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

    public function test_checkout_creates_byl_invoice_with_correct_total(): void
    {
        $this->enableByl();

        Http::fake([
            'byl.mn/api/v1/projects/42/invoices' => Http::response([
                'data' => ['id' => 777, 'status' => 'open', 'url' => 'https://byl.mn/h/inv/777/abc'],
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
            ->assertJsonPath('data.invoice_url', 'https://byl.mn/h/inv/777/abc');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/projects/42/invoices')
            && $request->hasHeader('Authorization', 'Bearer byl_test_token'));
    }

    public function test_webhook_activates_plan_and_campaign(): void
    {
        $this->enableByl();

        Http::fake([
            'byl.mn/api/v1/projects/42/invoices' => Http::response(['data' => ['id' => 777, 'url' => 'https://byl.mn/x']]),
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

        $body = json_encode(['type' => 'invoice.paid', 'data' => ['object' => ['id' => 777, 'status' => 'paid']]]);
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

        $body = json_encode(['type' => 'invoice.paid', 'data' => ['object' => ['id' => 1]]]);

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

        // 4 дэх нь дараалалд орно
        $this->actingAs($this->owner)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'campaigns' => [[
                'type' => 'category_featured',
                'business_id' => $this->business->id,
                'category_id' => $this->category->id,
                'district' => 'Баянзүрх',
                'days' => 7,
            ]],
        ])->assertCreated();

        $this->assertSame('queued', Campaign::latest('id')->first()->status);

        // Slots endpoint зөв тоолно
        $this->actingAs($this->owner)->getJson('/api/v1/slots?type=category_featured&category_id='.$this->category->id.'&district='.urlencode('Баянзүрх'))
            ->assertOk()
            ->assertJsonPath('occupied', 3)
            ->assertJsonPath('queued', 1);
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
            'byl.mn/api/v1/projects/42/invoices' => Http::response(['data' => ['id' => 777, 'url' => 'https://byl.mn/x', 'status' => 'open']]),
            'byl.mn/api/v1/projects/42/invoices/777/void' => Http::response(['data' => ['id' => 777, 'status' => 'void']]),
            'byl.mn/api/v1/projects/42/invoices/777' => Http::response(['data' => ['id' => 777, 'status' => 'void']]),
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

    public function test_only_owner_can_checkout_for_organization(): void
    {
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->postJson('/api/v1/checkout', [
            'organization_id' => $this->organization->id,
            'plan' => 'standard',
        ])->assertForbidden();
    }
}
