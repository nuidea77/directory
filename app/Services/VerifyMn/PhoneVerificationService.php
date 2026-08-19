<?php

namespace App\Services\VerifyMn;

use App\Models\PhoneVerification;
use Illuminate\Support\Str;

/**
 * Orchestrates phone verification sessions backed by verify.mn.
 *
 * When verify.mn is disabled (local development without an API key) the
 * verification is marked verified immediately so the rest of the flow can be
 * exercised without real SMS traffic.
 */
class PhoneVerificationService
{
    public function __construct(protected VerifyMnClient $client)
    {
    }

    /**
     * Start a verification for a phone number. Returns the local record whose
     * uuid/sms_uri/display_instruction the frontend needs.
     *
     * @param  array<string, mixed>  $meta  purpose-specific payload (e.g. pending registration data)
     */
    public function start(string $phone, string $purpose, array $meta = []): PhoneVerification
    {
        // Invalidate previous pending attempts for the same phone+purpose so
        // only one session can complete.
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
            // Dev mode: no real SMS round-trip available.
            $verification->update([
                'status' => 'verified',
                'verified_at' => now(),
                'display_instruction' => 'DEV горим: баталгаажуулалт автоматаар амжилттай боллоо',
            ]);

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
     * Check the authoritative status on verify.mn and sync the local record.
     * Polling is throttled to the provider's minimum interval (3 seconds).
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

        // verify.mn rate-limits session polling: never hit it faster than every 3s.
        if ($verification->last_checked_at !== null && $verification->last_checked_at->diffInSeconds(now()) < 3) {
            return $verification;
        }

        $verification->forceFill(['last_checked_at' => now()])->save();

        $session = $this->client->getSession($verification->session_id);

        if (($session['sessionStatus'] ?? null) === 'VERIFIED') {
            $verification->update([
                'status' => 'verified',
                'verified_at' => isset($session['verifiedAt']) ? now()->parse($session['verifiedAt']) : now(),
            ]);
        }

        return $verification->refresh();
    }
}
