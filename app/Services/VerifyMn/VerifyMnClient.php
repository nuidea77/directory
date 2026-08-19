<?php

namespace App\Services\VerifyMn;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Client for verify.mn — Mongolia-only Mobile-Originated (MO) SMS verification.
 *
 * Flow:
 *  1. createSession(phone, text, callbackUrl) — verify.mn returns sessionId,
 *     smsUri (sms:144773?body=...), displayInstruction and expiresAt (~5 min TTL).
 *  2. The user sends the SMS from their phone to shortcode 144773.
 *  3. verify.mn fires a GET request (no body, no signature) to the callback URL.
 *     The callback is only a "check now" hint — never trust it on its own.
 *  4. getSession(sessionId) is the source of truth: sessionStatus === VERIFIED.
 *     Minimum polling interval is 3 seconds (faster than 2s returns 429).
 */
class VerifyMnClient
{
    public function createSession(string $phone, string $text, string $callbackUrl): array
    {
        $response = $this->http()
            ->post('/sessions', [
                'phone' => $phone,
                'text' => $text,
                'callback' => $callbackUrl,
            ])
            ->throw();

        return $response->json();
    }

    public function getSession(string $sessionId): array
    {
        $response = $this->http()
            ->get('/sessions/'.$sessionId)
            ->throw();

        return $response->json();
    }

    public function isVerified(string $sessionId): bool
    {
        return ($this->getSession($sessionId)['sessionStatus'] ?? null) === 'VERIFIED';
    }

    public function enabled(): bool
    {
        return (bool) config('services.verify_mn.enabled')
            && filled(config('services.verify_mn.api_key'));
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(config('services.verify_mn.base_url'))
            ->withToken(config('services.verify_mn.api_key'))
            ->acceptJson()
            ->timeout(15);
    }
}
