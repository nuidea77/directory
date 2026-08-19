<?php

namespace App\Services\VerifyMn;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * verify.mn (MO SMS) баталгаажуулалтын оркестрация.
 *
 * Purposes:
 *  - register       → амжилттай болмогц хэрэглэгчийн phone_verified_at тавигдана
 *  - login          → амжилттай болмогц нэг удаагийн token олгох боломж нээгдэнэ
 *  - reset_password → амжилттай болмогц шинэ нууц үг тохируулна
 *
 * verify.mn түлхүүргүй орчинд (local dev) шууд verified болгоно.
 */
class PhoneVerificationService
{
    public function __construct(protected VerifyMnClient $client)
    {
    }

    public function start(string $phone, string $purpose, array $meta = []): PhoneVerification
    {
        PhoneVerification::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('status', 'pending')
            ->update(['status' => 'expired']);

        $verification = PhoneVerification::create([
            'uuid' => (string) Str::uuid(),
            'phone' => $phone,
            'purpose' => $purpose,
            'code' => (string) random_int(100000, 999999),
            'status' => 'pending',
            'callback_token' => Str::random(48),
            'meta' => $meta,
            'expires_at' => now()->addMinutes(5),
        ]);

        if (! $this->client->enabled()) {
            // Fail-open хориотой: авто-баталгаажуулалт зөвхөн local/testing орчинд.
            // Production дээр түлхүүр дутуу бол нээлттэй орхихын оронд алдаа өгнө.
            if (! app()->environment('local', 'testing')) {
                Log::error('verify.mn: API түлхүүр тохируулаагүй байхад баталгаажуулалт эхлүүлэх оролдлого');

                throw ValidationException::withMessages([
                    'phone' => 'Баталгаажуулалтын үйлчилгээ түр ажиллахгүй байна. Хэсэг хугацааны дараа дахин оролдоно уу.',
                ]);
            }

            $this->markVerified($verification);

            return $verification->refresh();
        }

        $callbackUrl = route('webhooks.verify-mn', [
            'verification' => $verification->uuid,
            'token' => $verification->callback_token,
        ]);

        try {
            $session = $this->client->createSession($phone, $verification->code, $callbackUrl);
        } catch (RequestException $e) {
            // 401 (буруу түлхүүр) / 5xx — API түлхүүрийг хэзээ ч лог руу оруулахгүй
            Log::error('verify.mn: session үүсгэж чадсангүй', [
                'status' => $e->response?->status(),
                'phone' => $phone,
            ]);

            $verification->update(['status' => 'failed']);

            throw ValidationException::withMessages([
                'phone' => 'Баталгаажуулалтын үйлчилгээ түр ажиллахгүй байна. Хэсэг хугацааны дараа дахин оролдоно уу.',
            ]);
        }

        $verification->update([
            'session_id' => $session['sessionId'] ?? null,
            'sms_uri' => $session['smsUri'] ?? null,
            'display_instruction' => $session['displayInstruction'] ?? null,
            'expires_at' => isset($session['expiresAt']) ? now()->parse($session['expiresAt']) : $verification->expires_at,
        ]);

        return $verification->refresh();
    }

    /**
     * Албан ёсны төлвийг verify.mn-ээс шалгаж локал бичлэгийг sync хийнэ.
     * Polling нь провайдерын доод хязгаараар (3с) хязгаарлагдана.
     */
    public function check(PhoneVerification $verification): PhoneVerification
    {
        if ($verification->isVerified()) {
            return $verification;
        }

        if ($verification->isExpired()) {
            if ($verification->status === 'pending') {
                $verification->update(['status' => 'expired']);
            }

            return $verification->refresh();
        }

        if (! $this->client->enabled() || $verification->session_id === null) {
            return $verification;
        }

        if ($verification->last_checked_at !== null && $verification->last_checked_at->diffInSeconds(now()) < 3) {
            return $verification;
        }

        $verification->forceFill(['last_checked_at' => now()])->save();

        $session = $this->client->getSession($verification->session_id);

        if (($session['sessionStatus'] ?? null) === 'VERIFIED') {
            $this->markVerified($verification, $session['verifiedAt'] ?? null);
        }

        return $verification->refresh();
    }

    protected function markVerified(PhoneVerification $verification, ?string $verifiedAt = null): void
    {
        $verification->update([
            'status' => 'verified',
            'verified_at' => $verifiedAt ? now()->parse($verifiedAt) : now(),
        ]);

        // Purpose-оос хамаарсан side effect-үүд (idempotent)
        match ($verification->purpose) {
            'register' => User::where('phone', $verification->phone)
                ->whereNull('phone_verified_at')
                ->update(['phone_verified_at' => now()]),
            default => null,
        };
    }
}
