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

    public function test_registration_creates_user_and_verify_session(): void
    {
        $this->fakeVerifyMn();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Энхжин',
            'phone' => '99112233',
            'password' => 'secret123',
        ]);

        // Токен олгохгүй — зөвхөн баталгаажсаны дараа (verificationStatus) олгоно
        $response->assertCreated()
            ->assertJsonStructure(['user', 'verification'])
            ->assertJsonMissingPath('token')
            ->assertJsonPath('verification.status', 'pending')
            ->assertJsonPath('verification.sms_uri', 'sms:144773?body=482916')
            ->assertJsonPath('user.phone_verified', false);

        $this->assertDatabaseHas('users', ['phone' => '99112233']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/sessions')
            && $request['phone'] === '99112233'
            && $request->hasHeader('Authorization', 'Bearer vrf_test_key'));
    }

    public function test_phone_marked_verified_after_session_verified(): void
    {
        $this->fakeVerifyMn('VERIFIED');

        $start = $this->postJson('/api/v1/auth/register', [
            'name' => 'Энхжин', 'phone' => '99112233', 'password' => 'secret123',
        ]);

        $uuid = $start->json('verification.uuid');

        // Баталгаажмагц нэвтрэх токен олгоно (бүртгэлийн урсгал)
        $this->getJson("/api/v1/auth/verifications/{$uuid}")
            ->assertOk()
            ->assertJsonPath('verification.status', 'verified')
            ->assertJsonStructure(['token', 'user'])
            ->assertJsonPath('user.phone_verified', true);

        $this->assertNotNull(User::where('phone', '99112233')->first()->phone_verified_at);

        // Хоёр дахь poll токен дахин олгохгүй (consumed)
        $this->getJson("/api/v1/auth/verifications/{$uuid}")->assertOk()->assertJsonMissingPath('token');
    }

    public function test_reregister_restarts_verification_for_unverified_phone(): void
    {
        $this->fakeVerifyMn();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Энхжин', 'phone' => '99112233', 'password' => 'secret123',
        ])->assertCreated();

        // Ижил нууц үгтэй бол шинэ session эхлүүлнэ (хэрэглэгч давхардахгүй)
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Энхжин', 'phone' => '99112233', 'password' => 'secret123',
        ])->assertCreated()->assertJsonPath('verification.status', 'pending');

        $this->assertSame(1, User::where('phone', '99112233')->count());

        // Буруу нууц үгтэй бол татгалзана
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Энхжин', 'phone' => '99112233', 'password' => 'oordifferent1',
        ])->assertStatus(422);

        // Баталгаажсан дугаараар дахин бүртгүүлэхийг хориглоно
        User::where('phone', '99112233')->update(['phone_verified_at' => now()]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Энхжин', 'phone' => '99112233', 'password' => 'secret123',
        ])->assertStatus(422);
    }

    public function test_expired_session_treated_as_failure(): void
    {
        // verify.mn PENDING хэвээр — хугацаа нь дуусна
        $this->fakeVerifyMn('PENDING');

        $start = $this->postJson('/api/v1/auth/register', [
            'name' => 'Энхжин', 'phone' => '99112233', 'password' => 'secret123',
        ]);

        $uuid = $start->json('verification.uuid');

        $this->travel(6)->minutes();

        $this->getJson("/api/v1/auth/verifications/{$uuid}")
            ->assertOk()
            ->assertJsonPath('verification.status', 'expired')
            ->assertJsonMissingPath('token');

        $this->assertNull(User::where('phone', '99112233')->first()->phone_verified_at);
    }

    public function test_missing_api_key_never_silently_bypasses_verification(): void
    {
        // Идэвхтэй (enabled=true) боловч түлхүүргүй — авто-баталгаажуулалт ХИЙХГҮЙ, алдаа өгнө
        config(['services.verify_mn.enabled' => true, 'services.verify_mn.api_key' => null]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Энхжин', 'phone' => '99112233', 'password' => 'secret123',
        ])->assertStatus(422)->assertJsonValidationErrors(['phone']);

        $this->assertNull(User::where('phone', '99112233')->first()?->phone_verified_at);
    }

    public function test_bad_api_key_returns_validation_error_not_500(): void
    {
        Http::fake([
            'api.verify.mn/sessions' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Энхжин', 'phone' => '99112233', 'password' => 'secret123',
        ])->assertStatus(422);
    }

    public function test_unverified_user_cannot_review_or_message(): void
    {
        $user = User::factory()->unverified()->create();
        $branch = \App\Models\Branch::factory()->create();

        $this->actingAs($user)->postJson("/api/v1/branches/{$branch->id}/reviews", ['rating' => 5])
            ->assertForbidden()
            ->assertJsonPath('code', 'phone_unverified');
    }

    public function test_sms_login_issues_token_when_verified(): void
    {
        $this->fakeVerifyMn('VERIFIED');

        User::factory()->create(['phone' => '99112233']);

        $start = $this->postJson('/api/v1/auth/login-sms', ['phone' => '99112233']);
        $start->assertOk();

        $uuid = $start->json('verification.uuid');

        $poll = $this->getJson("/api/v1/auth/verifications/{$uuid}");
        $poll->assertOk()->assertJsonStructure(['token', 'user']);

        // Хоёр дахь poll token дахин олгохгүй (consumed)
        $this->getJson("/api/v1/auth/verifications/{$uuid}")->assertOk()->assertJsonMissingPath('token');
    }

    public function test_polling_respects_provider_min_interval(): void
    {
        $this->fakeVerifyMn();

        $start = $this->postJson('/api/v1/auth/register', [
            'name' => 'Т', 'phone' => '99112233', 'password' => 'secret123',
        ]);

        $uuid = $start->json('verification.uuid');

        $this->getJson("/api/v1/auth/verifications/{$uuid}");
        $this->getJson("/api/v1/auth/verifications/{$uuid}");

        Http::assertSentCount(2); // 1 createSession + 1 getSession (хоёр дахь нь 3с дотор тул алгасна)
    }

    public function test_callback_requires_valid_token(): void
    {
        $this->fakeVerifyMn('VERIFIED');

        $start = $this->postJson('/api/v1/auth/register', [
            'name' => 'Т', 'phone' => '99112233', 'password' => 'secret123',
        ]);

        $verification = PhoneVerification::where('uuid', $start->json('verification.uuid'))->first();

        $this->get("/webhooks/verify-mn/{$verification->uuid}?token=wrong")->assertOk();
        $this->assertSame('pending', $verification->refresh()->status);

        $this->get("/webhooks/verify-mn/{$verification->uuid}?token={$verification->callback_token}")->assertOk();
        $this->assertSame('verified', $verification->refresh()->status);
    }

    public function test_login_locks_after_three_failed_attempts(): void
    {
        User::factory()->create(['phone' => '88001122', 'password' => 'correct123']);

        foreach (range(1, 3) as $i) {
            $this->postJson('/api/v1/auth/login', ['phone' => '88001122', 'password' => 'wrong'.$i])->assertStatus(422);
        }

        // 4 дэх оролдлого — зөв нууц үгтэй ч түгжигдсэн байна
        $this->postJson('/api/v1/auth/login', ['phone' => '88001122', 'password' => 'correct123'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_password_login_and_reset_flow(): void
    {
        $this->fakeVerifyMn('VERIFIED');

        User::factory()->create(['phone' => '88001122', 'password' => 'oldpass123']);

        $this->postJson('/api/v1/auth/login', ['phone' => '88001122', 'password' => 'wrong'])->assertStatus(422);
        $this->postJson('/api/v1/auth/login', ['phone' => '88001122', 'password' => 'oldpass123'])->assertOk()->assertJsonStructure(['token']);

        $start = $this->postJson('/api/v1/auth/reset', ['phone' => '88001122']);
        $uuid = $start->json('verification.uuid');

        $this->getJson("/api/v1/auth/verifications/{$uuid}");

        $this->postJson('/api/v1/auth/reset/confirm', [
            'verification_uuid' => $uuid,
            'password' => 'newpass123',
        ])->assertOk()->assertJsonStructure(['token']);

        $this->postJson('/api/v1/auth/login', ['phone' => '88001122', 'password' => 'newpass123'])->assertOk();

        // Давхар ашиглалт хориотой
        $this->postJson('/api/v1/auth/reset/confirm', [
            'verification_uuid' => $uuid,
            'password' => 'again12345',
        ])->assertStatus(422);
    }
}
