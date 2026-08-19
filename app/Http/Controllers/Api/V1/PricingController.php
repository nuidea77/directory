<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PricingController extends Controller
{
    /**
     * Үнийн хуудасны бүх өгөгдөл: эрхийн бичгүүд + сурталчилгааны багцууд.
     */
    public function index(): JsonResponse
    {
        // Идэвхгүй болгосон эрхийг зарлахгүй — эс бөгөөс худалдаж авах гэхэд
        // checkout 422 буцаадаг «үзүүлээд зарахгүй» байдал үүсдэг
        $plans = collect(config('billing.plans'))
            ->filter(fn (array $plan) => $plan['is_active'] ?? true)
            ->map(fn (array $plan, string $key) => [
            'key' => $key,
            'name' => $plan['name'],
            'price' => $plan['price'],
            'price_monthly' => $plan['price_monthly'] ?? null,
            'term_years' => $plan['term_years'],
            'limits' => $plan['limits'],
            'analytics' => $plan['analytics'],
            'top_list' => $plan['top_list'],
            'verified_badge' => $plan['verified_badge'],
        ])->values();

        $ads = collect(config('billing.ads'))->map(fn (array $ad, string $key) => [
            'key' => $key,
            'name' => $ad['name'],
            'slots' => $ad['slots'],
            'prices' => $ad['prices'],
            'business_plan_discount' => $ad['business_plan_discount'],
        ])->values();

        return response()->json([
            'plans' => $plans,
            'ads' => $ads,
            'branch_addon_price' => config('billing.branch_addon_price'),
        ]);
    }
}
