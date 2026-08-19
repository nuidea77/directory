<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Billing\BillingService;
use App\Services\Byl\BylClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * byl.mn webhook — Byl-Signature (HMAC-SHA256, raw body) шалгана.
 */
class BylWebhookController extends Controller
{
    public function __invoke(Request $request, BylClient $byl, BillingService $billing): JsonResponse
    {
        if (! $byl->verifyWebhookSignature($request->getContent(), $request->header('Byl-Signature'))) {
            Log::warning('byl.mn webhook: invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = $request->json()->all();
        $type = $event['type'] ?? null;
        $object = $event['data']['object'] ?? [];

        if ($type === 'checkout.completed') {
            $order = Order::where('byl_checkout_id', $object['id'] ?? 0)->first();

            if ($order === null) {
                Log::info('byl.mn webhook: no matching order', ['type' => $type, 'checkout_id' => $object['id'] ?? null]);

                return response()->json(['ok' => true]);
            }

            $billing->markPaid($order, $object);
        }

        return response()->json(['ok' => true]);
    }
}
