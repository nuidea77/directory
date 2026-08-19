<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BylPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.byl.token' => 'byl_test_token',
            'services.byl.project_id' => '42',
            'services.byl.webhook_secret' => 'whsec_test',
        ]);
    }

    public function test_feature_payment_creates_byl_invoice(): void
    {
        Http::fake([
            'byl.mn/api/v1/projects/42/invoices' => Http::response([
                'data' => [
                    'id' => 777,
                    'status' => 'open',
                    'amount' => 15000,
                    'url' => 'https://byl.mn/h/inv/777/abc',
                ],
            ]),
        ]);

        $user = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson("/api/v1/my/listings/{$listing->id}/feature", [
            'plan' => 'featured_7',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.invoice_url', 'https://byl.mn/h/inv/777/abc');

        $this->assertDatabaseHas('payments', [
            'listing_id' => $listing->id,
            'byl_invoice_id' => 777,
            'status' => 'pending',
            'amount' => 15000,
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/projects/42/invoices')
            && $request['amount'] === 15000
            && $request->hasHeader('Authorization', 'Bearer byl_test_token'));
    }

    public function test_only_owner_can_feature_a_listing(): void
    {
        $listing = Listing::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->postJson("/api/v1/my/listings/{$listing->id}/feature", [
            'plan' => 'featured_7',
        ])->assertForbidden();
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $body = json_encode(['type' => 'invoice.paid', 'data' => ['object' => ['id' => 777]]]);

        $this->call('POST', '/webhooks/byl', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_BYL_SIGNATURE' => 'invalid',
        ], $body)->assertStatus(400);
    }

    public function test_webhook_marks_payment_paid_and_features_listing(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $user->id]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'provider' => 'byl',
            'plan' => 'featured_7',
            'byl_invoice_id' => 777,
            'amount' => 15000,
            'status' => 'pending',
        ]);

        $body = json_encode([
            'id' => 1,
            'project_id' => 42,
            'type' => 'invoice.paid',
            'data' => ['object' => ['id' => 777, 'status' => 'paid', 'amount' => 15000]],
        ]);

        $signature = hash_hmac('sha256', $body, 'whsec_test');

        $this->call('POST', '/webhooks/byl', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_BYL_SIGNATURE' => $signature,
        ], $body)->assertOk();

        $this->assertSame('paid', $payment->refresh()->status);
        $this->assertNotNull($payment->paid_at);

        $listing->refresh();
        $this->assertTrue($listing->isFeatured());
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $listing->featured_until->timestamp, 5);
    }

    public function test_webhook_is_idempotent_for_duplicate_deliveries(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->create(['user_id' => $user->id]);

        $payment = Payment::create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'provider' => 'byl',
            'plan' => 'featured_7',
            'byl_invoice_id' => 777,
            'amount' => 15000,
            'status' => 'pending',
        ]);

        $body = json_encode([
            'type' => 'invoice.paid',
            'data' => ['object' => ['id' => 777, 'status' => 'paid']],
        ]);
        $signature = hash_hmac('sha256', $body, 'whsec_test');
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_BYL_SIGNATURE' => $signature];

        $this->call('POST', '/webhooks/byl', [], [], [], $headers, $body)->assertOk();
        $firstExpiry = $listing->refresh()->featured_until;

        // Duplicate delivery must not extend the featured window again.
        $this->call('POST', '/webhooks/byl', [], [], [], $headers, $body)->assertOk();

        $this->assertEquals($firstExpiry, $listing->refresh()->featured_until);
    }
}
