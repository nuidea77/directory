<?php

namespace App\Services\VerifyMn;

use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Support\Str;

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
            $this->markVerified($verification);

            return $verification->refresh();
        }

        $callbackUrl = route('webhooks.verify-mn', [
            'verification' => $verification->uuid,
            'token' => $verification->callback_token,
        ]);

        $session = $this->client->createSession($phone, $verification->code, $callbackUrl);

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
