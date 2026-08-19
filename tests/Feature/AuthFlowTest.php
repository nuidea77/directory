<?php

namespace Tests\Feature;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.verify_mn.enabled' => true,
            'services.verify_mn.api_key' => 'vrf_test_key',
        ]);
    }

    protected function fakeVerifyMn(string $status = 'PENDING'): void
    {
        Http::fake([
            'api.verify.mn/sessions' => Http::response([
                'sessionId' => 'sess-123',
                'phone' => '99112233',
                'shortcode' => '144773',
                'smsUri' => 'sms:144773?body=482916',
                'displayInstruction' => '144773 дугаарт "482916" гэж SMS илгээнэ үү',
                'expiresAt' => now()->addMinutes(5)->toIso8601String(),
            ]),
            'api.verify.mn/sessions/*' => Http::response([
                'sessionId' => 'sess-123',
                'sessionStatus' => $status,
                'verifiedAt' => $status === 'VERIFIED' ? now()->toIso8601String() : null,
                'expiresAt' => now()->addMinutes(5)->toIso8601String(),
            ]),
        ]);
    }

    public function test_registration_creates_verify_mn_session(): void
    {
        $this->fakeVerifyMn();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Бат',
            'phone' => '99112233',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('verification.status', 'pending')
            ->assertJsonPath('verification.sms_uri', 'sms:144773?body=482916')
            ->assertJsonMissingPath('token');

        // The user must NOT exist until the phone is verified.
        $this->assertDatabaseMissing('users', ['phone' => '99112233']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sessions')
            && $request['phone'] === '99112233'
            && $request->hasHeader('Authorization', 'Bearer vrf_test_key'));
    }

    public function test_user_created_and_token_issued_once_session_verified(): void
    {
        $this->fakeVerifyMn('VERIFIED');

        $start = $this->postJson('/api/v1/auth/register', [
            'name' => 'Бат',
            'phone' => '99112233',
            'password' => 'secret123',
        ]);

        $uuid = $start->json('verification.uuid');

        $poll = $this->getJson("/api/v1/auth/verifications/{$uuid}");

        $poll->assertOk()
            ->assertJsonPath('verification.status', 'verified')
            ->assertJsonStructure(['token', 'user']);

        $this->assertDatabaseHas('users', ['phone' => '99112233']);
        $this->assertNotNull(User::where('phone', '99112233')->first()->phone_verified_at);

        // Second poll must not issue a second token (consumed).
        $again = $this->getJson("/api/v1/auth/verifications/{$uuid}");
        $again->assertOk()->assertJsonMissingPath('token');
    }

    public function test_polling_is_throttled_to_provider_minimum_interval(): void
    {
        $this->fakeVerifyMn();

        $start = $this->postJson('/api/v1/auth/register', [
            'name' => 'Бат',
            'phone' => '99112233',
            'password' => 'secret123',
        ]);

        $uuid = $start->json('verification.uuid');

        // Two immediate polls: only one upstream status request may go out.
        $this->getJson("/api/v1/auth/verifications/{$uuid}");
        $this->getJson("/api/v1/auth/verifications/{$uuid}");

        Http::assertSentCount(2); // 1 createSession + 1 getSession
    }

    public function test_callback_requires_valid_token_and_verifies(): void
    {
        $this->fakeVerifyMn('VERIFIED');

        $start = $this->postJson('/api/v1/auth/register', [
            'name' => 'Бат',
            'phone' => '99112233',
            'password' => 'secret123',
        ]);

        $verification = PhoneVerification::where('uuid', $start->json('verification.uuid'))->first();

        // Wrong token: nothing changes (still 200 so the provider stops retrying).
        $this->get("/webhooks/verify-mn/{$verification->uuid}?token=wrong")->assertOk();
        $this->assertSame('pending', $verification->refresh()->status);

        // Correct token: the session is re-checked and marked verified.
        $this->get("/webhooks/verify-mn/{$verification->uuid}?token={$verification->callback_token}")->assertOk();
        $this->assertSame('verified', $verification->refresh()->status);
    }

    public function test_login_with_phone_and_password(): void
    {
        $user = User::factory()->create(['phone' => '88001122', 'password' => 'secret123']);

        $this->postJson('/api/v1/auth/login', ['phone' => '88001122', 'password' => 'wrong'])
            ->assertStatus(422);

        $this->postJson('/api/v1/auth/login', ['phone' => '88001122', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'phone']]);
    }

    public function test_unverified_phone_cannot_login(): void
    {
        User::factory()->unverified()->create(['phone' => '88001122', 'password' => 'secret123']);

        $this->postJson('/api/v1/auth/login', ['phone' => '88001122', 'password' => 'secret123'])
            ->assertStatus(422);
    }

    public function test_password_reset_flow(): void
    {
        $this->fakeVerifyMn('VERIFIED');

        $user = User::factory()->create(['phone' => '88001122', 'password' => 'oldpass123']);

        $start = $this->postJson('/api/v1/auth/reset', ['phone' => '88001122']);
        $start->assertOk();

        $uuid = $start->json('verification.uuid');

        // Poll once so the local record syncs to verified.
        $this->getJson("/api/v1/auth/verifications/{$uuid}")->assertOk();

        $confirm = $this->postJson('/api/v1/auth/reset/confirm', [
            'verification_uuid' => $uuid,
            'password' => 'newpass123',
        ]);

        $confirm->assertOk()->assertJsonStructure(['token']);

        $this->postJson('/api/v1/auth/login', ['phone' => '88001122', 'password' => 'newpass123'])
            ->assertOk();

        // A second confirm with the same verification must fail (consumed).
        $this->postJson('/api/v1/auth/reset/confirm', [
            'verification_uuid' => $uuid,
            'password' => 'anotherpass123',
        ])->assertStatus(422);
    }
}
