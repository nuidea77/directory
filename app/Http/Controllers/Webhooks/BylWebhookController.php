<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Byl\BylClient;
use App\Services\Byl\FeaturePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * byl.mn webhook receiver. Every request is signed with HMAC-SHA256 over the
 * raw body, delivered in the Byl-Signature header.
 */
class BylWebhookController extends Controller
{
    public function __invoke(Request $request, BylClient $byl, FeaturePaymentService $payments): JsonResponse
    {
        if (! $byl->verifyWebhookSignature($request->getContent(), $request->header('Byl-Signature'))) {
            Log::warning('byl.mn webhook: invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->json()->all();
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];

        if (in_array($type, ['invoice.paid', 'invoice.void'], true)) {
            $payment = Payment::where('byl_invoice_id', $object['id'] ?? 0)->first();

            if ($payment === null) {
                Log::info('byl.mn webhook: no matching payment', ['type' => $type, 'invoice_id' => $object['id'] ?? null]);

                return response()->json(['ok' => true]);
            }

            if ($type === 'invoice.paid') {
                $payments->markPaid($payment, $object);
            } else {
                $payment->update(['status' => 'void', 'provider_payload' => $object]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
