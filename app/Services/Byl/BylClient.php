<?php

namespace App\Services\Byl;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Client for byl.mn — Mongolian payment aggregator (QPay, SocialPay, Pocket, Golomt).
 *
 * All endpoints live under https://byl.mn/api/v1/projects/:project_id and are
 * authenticated with a Bearer API token. Paid/void notifications arrive on the
 * webhook endpoint signed with HMAC-SHA256 in the Byl-Signature header.
 */
class BylClient
{
    /**
     * Create an invoice and get back a hosted payment URL.
     *
     * @return array{id: int, url: string, status: string, amount: mixed}
     */
    public function createInvoice(int $amount, string $description, ?int $customerId = null): array
    {
        $payload = [
            'amount' => $amount,
            'description' => $description,
            'auto_advance' => true,
        ];

        if ($customerId !== null) {
            $payload['customer_id'] = $customerId;
        }

        $response = $this->http()
            ->post($this->projectPath('/invoices'), $payload)
            ->throw();

        return $response->json('data') ?? $response->json();
    }

    public function getInvoice(int $invoiceId): array
    {
        $response = $this->http()
            ->get($this->projectPath('/invoices/'.$invoiceId))
            ->throw();

        return $response->json('data') ?? $response->json();
    }

    public function voidInvoice(int $invoiceId): array
    {
        $response = $this->http()
            ->post($this->projectPath('/invoices/'.$invoiceId.'/void'))
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
            ->timeout(20);
    }
}
