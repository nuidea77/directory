<?php

namespace App\Services\Byl;

use App\Models\Listing;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates byl.mn invoices for "featured listing" plans and applies the
 * upgrade once the invoice is paid (via webhook or status polling).
 */
class FeaturePaymentService
{
    public function __construct(protected BylClient $byl)
    {
    }

    public function createForPlan(User $user, Listing $listing, string $plan): Payment
    {
        $planConfig = config("billing.plans.{$plan}");

        if ($planConfig === null) {
            throw ValidationException::withMessages(['plan' => 'Буруу төлөвлөгөө сонгосон байна.']);
        }

        $payment = Payment::create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
            'provider' => 'byl',
            'plan' => $plan,
            'amount' => $planConfig['amount'],
            'status' => 'pending',
        ]);

        if (! $this->byl->enabled()) {
            // Dev mode: mark paid immediately so the flow can be exercised locally.
            $this->markPaid($payment, ['dev' => true]);

            return $payment->refresh();
        }

        $invoice = $this->byl->createInvoice(
            $planConfig['amount'],
            sprintf('%s — %s (#%d)', $planConfig['name'], $listing->name, $payment->id),
        );

        $payment->update([
            'byl_invoice_id' => $invoice['id'] ?? null,
            'invoice_url' => $invoice['url'] ?? null,
            'provider_payload' => $invoice,
        ]);

        return $payment->refresh();
    }

    /**
     * Sync a pending payment's status against the byl.mn API. Used both from
     * the webhook (after signature verification) and from client-side polling.
     */
    public function sync(Payment $payment): Payment
    {
        if ($payment->status !== 'pending' || $payment->byl_invoice_id === null || ! $this->byl->enabled()) {
            return $payment;
        }

        $invoice = $this->byl->getInvoice($payment->byl_invoice_id);

        match ($invoice['status'] ?? null) {
            'paid' => $this->markPaid($payment, $invoice),
            'void' => $payment->update(['status' => 'void', 'provider_payload' => $invoice]),
            default => null,
        };

        return $payment->refresh();
    }

    public function markPaid(Payment $payment, array $payload = []): void
    {
        DB::transaction(function () use ($payment, $payload) {
            $payment = Payment::lockForUpdate()->find($payment->id);

            if ($payment === null || $payment->status === 'paid') {
                return; // idempotent: webhooks may be delivered more than once
            }

            $payment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'provider_payload' => $payload ?: $payment->provider_payload,
            ]);

            $listing = $payment->listing;
            $days = (int) config("billing.plans.{$payment->plan}.days", 0);

            if ($listing !== null && $days > 0) {
                // Extend from the current expiry when it is still in the future.
                $from = $listing->featured_until !== null && $listing->featured_until->isFuture()
                    ? $listing->featured_until
                    : now();

                $listing->update(['featured_until' => $from->copy()->addDays($days)]);
            }
        });
    }
}
