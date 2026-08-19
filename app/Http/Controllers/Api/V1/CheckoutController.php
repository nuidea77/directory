<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\OrderResource;
use App\Models\Business;
use App\Models\Order;
use App\Models\Organization;
use App\Services\Billing\BillingService;
use App\Services\Billing\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CheckoutController extends Controller
{
    public function __construct(
        protected BillingService $billing,
        protected CampaignService $campaigns,
    ) {
    }

    /**
     * Захиалга үүсгэх: эрхийн бичиг + салбарын нэмэлт + онцлох байршил →
     * нэг byl.mn нэхэмжлэх.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:organizations,id'],
            'plan' => ['nullable', 'in:standard,business'],
            'extra_branches' => ['nullable', 'integer', 'min:0', 'max:100'],
            'campaigns' => ['nullable', 'array', 'max:5'],
            'campaigns.*.type' => ['required', 'in:category_featured,home_featured,keyword'],
            'campaigns.*.business_id' => ['required', 'integer', 'exists:businesses,id'],
            'campaigns.*.branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'campaigns.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'campaigns.*.district' => ['nullable', 'string', 'max:80'],
            'campaigns.*.city' => ['nullable', 'string', 'max:80'],
            'campaigns.*.keyword' => ['nullable', 'string', 'max:80'],
            'campaigns.*.days' => ['required', 'integer', 'in:7,14,30'],
        ]);

        $organization = Organization::findOrFail($data['organization_id']);
        abort_unless($organization->owner_id === $request->user()->id, 403);

        // Кампанит ажлын бизнесүүд өөрийнх байх ёстой
        foreach ($data['campaigns'] ?? [] as $c) {
            $business = Business::findOrFail($c['business_id']);
            abort_unless($business->organization_id === $organization->id, 403);
        }

        $order = $this->billing->createOrder($request->user(), $organization, $data);

        return response()->json(['data' => new OrderResource($order->load('items'))], 201);
    }

    /**
     * Захиалгын төлөв poll хийх (byl.mn-тэй sync).
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order = $this->billing->sync($order);

        return response()->json(['data' => new OrderResource($order->load('items'))]);
    }

    /**
     * Нэхэмжлэхийн түүх.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $orders = $request->user()->orders()->with('items')->latest()->paginate(20);

        return OrderResource::collection($orders);
    }

    /**
     * Байгууллагын кампанит ажлууд (Эрх ба сурталчилгаа таб).
     */
    public function campaigns(Request $request, Organization $organization): JsonResponse
    {
        abort_unless($organization->owner_id === $request->user()->id, 403);

        $this->campaigns->sync();

        $campaigns = $organization->campaigns()
            ->with(['business:id,name', 'category:id,name'])
            ->whereIn('status', ['active', 'queued', 'expired'])
            ->latest()
            ->limit(20)
            ->get();

        return response()->json(['data' => CampaignResource::collection($campaigns)]);
    }

    /**
     * Онцлох зайн боломж шалгах (8b дэлгэц): сул зай, дараалал, үнэ.
     */
    public function slots(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:category_featured,home_featured,keyword'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'district' => ['nullable', 'string', 'max:80'],
            'city' => ['nullable', 'string', 'max:80'],
            'keyword' => ['nullable', 'string', 'max:80'],
        ]);

        $state = $this->campaigns->slotState(
            $data['type'],
            $data['category_id'] ?? null,
            $data['district'] ?? null,
            $data['city'] ?? null,
            isset($data['keyword']) ? mb_strtolower(trim($data['keyword'])) : null,
        );

        $config = config("billing.ads.{$data['type']}");

        return response()->json([
            'total' => $state['total'],
            'occupied' => $state['occupied'],
            'queued' => $state['queued'],
            'slots' => collect(range(1, $state['total']))->map(function (int $n) use ($state) {
                $running = $state['running']->firstWhere('slot', $n);

                return [
                    'slot' => $n,
                    'available' => $running === null,
                    'occupied_until' => $running?->ends_at,
                ];
            }),
            'prices' => $config['prices'],
            'business_plan_discount' => $config['business_plan_discount'],
        ]);
    }
}
