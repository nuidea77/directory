<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Listing;
use App\Models\Payment;
use App\Services\Byl\FeaturePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentController extends Controller
{
    public function __construct(protected FeaturePaymentService $payments)
    {
    }

    /**
     * Available featured-listing plans.
     */
    public function plans(): JsonResponse
    {
        $plans = collect(config('billing.plans'))
            ->map(fn (array $plan, string $key) => [
                'key' => $key,
                'name' => $plan['name'],
                'days' => $plan['days'],
                'amount' => $plan['amount'],
            ])
            ->values();

        return response()->json(['data' => $plans]);
    }

    /**
     * Create a byl.mn invoice to feature a listing. Returns the payment with
     * the hosted invoice_url the user should be redirected to.
     */
    public function store(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->user_id === $request->user()->id, 403, 'Энэ бүртгэлийг удирдах эрх байхгүй.');

        $data = $request->validate([
            'plan' => ['required', 'string'],
        ]);

        $payment = $this->payments->createForPlan($request->user(), $listing, $data['plan']);

        return response()->json(['data' => new PaymentResource($payment)], 201);
    }

    /**
     * Poll a payment's status (also syncs against byl.mn while pending).
     */
    public function show(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->user_id === $request->user()->id, 403);

        $payment = $this->payments->sync($payment);

        return response()->json(['data' => new PaymentResource($payment)]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $payments = $request->user()->payments()->with('listing')->latest()->paginate(20);

        return PaymentResource::collection($payments);
    }
}
