<?php

namespace App\Services\Byl;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Client for byl.mn — Mongolian payment aggregator (QPay, SocialPay, Pocket, Golomt).
 *
 * We use the Checkout API (not Invoices) because checkouts support
 * success_url / cancel_url, so byl.mn sends the customer back to the site
 * after paying. All endpoints live under https://byl.mn/api/v1/projects/:project_id
 * and are authenticated with a Bearer API token. Paid notifications arrive on the
 * webhook endpoint (checkout.completed) signed with HMAC-SHA256 in the
 * Byl-Signature header.
 */
class BylClient
{
    /**
     * Create a checkout and get back a hosted payment URL.
     * byl.mn redirects the customer to $successUrl after payment
     * and to $cancelUrl if they abandon it.
     *
     * @return array{id: int, url: string, status: string, amount_total: mixed}
     */
    public function createCheckout(int $amount, string $itemName, string $orderNumber, string $successUrl, string $cancelUrl): array
    {
        $payload = [
            'items' => [[
                'price_data' => [
                    'unit_amount' => $amount,
                    'product_data' => ['name' => mb_substr($itemName, 0, 255)],
                ],
                'quantity' => 1,
            ]],
            'client_reference_id' => mb_substr($orderNumber, 0, 48),
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'email_collection' => false,
        ];

        $response = $this->http()
            ->post($this->projectPath('/checkouts'), $payload)
            ->throw();

        return $response->json('data') ?? $response->json();
    }

    /**
     * @return array{id: int, url: string, status: string} status: open|complete|expired
     */
    public function getCheckout(int $checkoutId): array
    {
        $response = $this->http()
            ->get($this->projectPath('/checkouts/'.$checkoutId))
            ->throw();

        return $response->json('data') ?? $response->json();
    }

    /**
     * Verify the HMAC-SHA256 webhook signature using a constant-time comparison.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.byl.webhook_secret');

        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }

    public function enabled(): bool
    {
        return filled(config('services.byl.token')) && filled(config('services.byl.project_id'));
    }

    protected function projectPath(string $path): string
    {
        return '/v1/projects/'.config('services.byl.project_id').$path;
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl(config('services.byl.base_url'))
            ->withToken(config('services.byl.token'))
            ->acceptJson()
            ->timeout(20)
            ->retry(2, 300, throw: false); // түр зуурын 5xx/timeout-д дахин оролдоно
    }
}
