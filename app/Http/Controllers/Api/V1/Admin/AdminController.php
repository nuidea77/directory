<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Notifications\BranchModerated;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Review;
use App\Models\User;
use App\Services\Billing\CampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Модерацын тойм: KPI + хүлээгдэж буй дараалал + дата чанар.
     */
    public function moderation(Request $request): JsonResponse
    {
        $pending = Branch::where('status', 'pending')
            ->with(['business.category', 'business.organization', 'images'])
            ->oldest('updated_at')
            ->paginate(10);

        $totalBranches = max(1, Branch::count());

        $kpis = [
            'pending' => Branch::where('status', 'pending')->count(),
            'approved_today' => Branch::where('status', 'active')->whereDate('updated_at', now())->count(),
            'rejected' => Branch::where('status', 'rejected')->count(),
            'flagged_reviews' => Review::where('status', 'flagged')->count(),
            'total_businesses' => Business::count(),
        ];

        $dataQuality = [
            ['name' => 'Координат тэмдэглэсэн', 'pct' => round(Branch::whereNotNull('lat')->count() / $totalBranches * 100)],
            ['name' => 'Цагийн хуваарьтай', 'pct' => round(Branch::whereNotNull('hours')->count() / $totalBranches * 100)],
            ['name' => '3+ зурагтай', 'pct' => round(Branch::has('images', '>=', 3)->count() / $totalBranches * 100)],
        ];

        return response()->json([
            'kpis' => $kpis,
            'queue' => BranchResource::collection($pending->getCollection()),
            'queue_total' => $pending->total(),
            'data_quality' => $dataQuality,
        ]);
    }

    public function approveBranch(Request $request, Branch $branch): JsonResponse
    {
        $branch->update(['status' => 'active', 'rejection_reason' => null]);

        $branch->business?->organization?->owner?->notify(new BranchModerated($branch, approved: true));

        return response()->json(['message' => 'Батлагдлаа.', 'data' => new BranchResource($branch)]);
    }

    public function rejectBranch(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $branch->update(['status' => 'rejected', 'rejection_reason' => $data['reason'] ?? null]);

        $branch->business?->organization?->owner?->notify(new BranchModerated($branch, approved: false));

        return response()->json(['message' => 'Татгалзлаа.', 'data' => new BranchResource($branch)]);
    }

    /**
     * Орлого, эрх, сурталчилгааны тайлан (9b дэлгэц).
     */
    public function revenue(Request $request, CampaignService $campaigns): JsonResponse
    {
        $campaigns->sync();

        $days = 30;
        $paidOrders = Order::where('status', 'paid')->where('paid_at', '>=', now()->subDays($days))->with('items')->get();
        $prevOrders = Order::where('status', 'paid')
            ->whereBetween('paid_at', [now()->subDays($days * 2), now()->subDays($days)])
            ->get();

        $revenue = $paidOrders->sum('total');
        $prevRevenue = $prevOrders->sum('total');

        $planRevenue = $paidOrders->flatMap->items->whereIn('type', ['plan', 'branch_addon'])->sum(fn ($i) => $i->amount - $i->discount);
        $adRevenue = $paidOrders->flatMap->items->where('type', 'campaign')->sum(fn ($i) => $i->amount - $i->discount);

        $paidOrgs = Organization::where('plan', '!=', 'free')->where('plan_expires_at', '>', now())->count();

        // Эрхийн бичгийн тархалт
        $planMix = collect(['free', 'standard', 'business'])->map(fn (string $plan) => [
            'plan' => $plan,
            'name' => config("billing.plans.{$plan}.name"),
            'count' => $plan === 'free'
                ? Organization::where(fn ($q) => $q->where('plan', 'free')->orWhere('plan_expires_at', '<=', now()))->count()
                : Organization::where('plan', $plan)->where('plan_expires_at', '>', now())->count(),
        ]);

        // Онцлох зайн бүртгэл (инвентор)
        $inventory = Campaign::query()->running()
            ->with('category:id,name')
            ->get()
            ->groupBy(fn (Campaign $c) => implode('|', [$c->type, $c->category_id, $c->district, $c->city, $c->keyword]))
            ->map(function ($group) {
                /** @var Campaign $first */
                $first = $group->first();
                $slots = (int) config("billing.ads.{$first->type}.slots", 3);
                $queued = Campaign::where('status', 'queued')
                    ->where('type', $first->type)
                    ->when($first->category_id, fn ($q) => $q->where('category_id', $first->category_id))
                    ->when($first->district, fn ($q) => $q->where('district', $first->district))
                    ->when($first->city, fn ($q) => $q->where('city', $first->city))
                    ->when($first->keyword, fn ($q) => $q->where('keyword', $first->keyword))
                    ->count();

                return [
                    'type' => $first->type,
                    'type_name' => config("billing.ads.{$first->type}.name"),
                    'label' => implode(' · ', array_filter([
                        $first->category?->name,
                        $first->district,
                        $first->city,
                        $first->keyword ? '“'.$first->keyword.'”' : null,
                    ])),
                    'occupied' => $group->count(),
                    'slots' => $slots,
                    'queued' => $queued,
                    'monthly_revenue' => $group->sum('price'),
                ];
            })
            ->values();

        return response()->json([
            'kpis' => [
                'revenue_30d' => $revenue,
                'revenue_delta' => $prevRevenue > 0 ? round(($revenue - $prevRevenue) / $prevRevenue * 100) : null,
                'paid_organizations' => $paidOrgs,
                'plan_revenue' => $planRevenue,
                'ad_revenue' => $adRevenue,
                'pending_orders' => Order::where('status', 'pending')->count(),
            ],
            'plan_mix' => $planMix,
            'inventory' => $inventory,
        ]);
    }

    /**
     * Бүх бизнесүүд (админ жагсаалт): эрхийн төрөл, хүлээгдэж буй
     * төлбөр/сурталчилгаа/модерац, ангилал, байршлаар (аймаг·сум/дүүрэг) шүүнэ.
     */
    public function businesses(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'plan' => ['nullable', 'in:free,standard,business'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'city' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'pending' => ['nullable', 'in:plan_order,ad,moderation'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $businesses = Business::with([
            'category:id,name',
            'organization:id,name,plan,plan_expires_at',
            'branches:id,business_id,city,district,status',
        ])
            ->withCount([
                'campaigns as pending_ads_count' => fn ($q) => $q->whereIn('status', ['pending_payment', 'queued']),
            ])
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where('name', 'like', '%'.addcslashes($term, '%_\\').'%'))
            ->when($filters['category_id'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->whereHas('branches', fn ($b) => $b->where('city', $city)))
            ->when($filters['district'] ?? null, fn ($q, $d) => $q->whereHas('branches', fn ($b) => $b->where('district', $d)))
            ->when($filters['plan'] ?? null, fn ($q, $plan) => $q->whereHas('organization', fn ($org) => $plan === 'free'
                ? $org->where(fn ($o) => $o->where('plan', 'free')->orWhereNull('plan_expires_at')->orWhere('plan_expires_at', '<=', now()))
                : $org->where('plan', $plan)->where('plan_expires_at', '>', now())))
            ->when($filters['pending'] ?? null, fn ($q, $pending) => match ($pending) {
                'plan_order' => $q->whereHas('organization.orders', fn ($o) => $o->where('status', 'pending')),
                'ad' => $q->whereHas('campaigns', fn ($c) => $c->whereIn('status', ['pending_payment', 'queued'])),
                'moderation' => $q->whereHas('branches', fn ($b) => $b->where('status', 'pending')),
            })
            ->latest()
            ->paginate(20);

        // Байгууллага бүрийн хүлээгдэж буй захиалгын тоо — нэг query
        $pendingOrders = Order::where('status', 'pending')
            ->whereIn('organization_id', $businesses->getCollection()->pluck('organization_id')->unique())
            ->selectRaw('organization_id, count(*) as cnt')
            ->groupBy('organization_id')
            ->pluck('cnt', 'organization_id');

        $rows = $businesses->getCollection()->map(function (Business $b) use ($pendingOrders) {
            $org = $b->organization;
            $plan = $org?->effectivePlan() ?? 'free';

            return [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'is_verified' => $b->is_verified,
                'category' => $b->category?->name,
                'organization' => $org?->name,
                'plan' => $plan,
                'plan_name' => config("billing.plans.{$plan}.name"),
                'plan_expires_at' => $plan !== 'free' ? $org?->plan_expires_at : null,
                'branches_count' => $b->branches->count(),
                'pending_branches_count' => $b->branches->where('status', 'pending')->count(),
                'pending_ads_count' => $b->pending_ads_count,
                'pending_orders_count' => (int) ($pendingOrders[$b->organization_id] ?? 0),
                'locations' => $b->branches
                    ->map(fn ($br) => implode(' · ', array_filter([$br->city, $br->district])))
                    ->filter()->unique()->values(),
                'created_at' => $b->created_at,
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => ['total' => $businesses->total(), 'current_page' => $businesses->currentPage(), 'last_page' => $businesses->lastPage()],
        ]);
    }

    /**
     * Гомдолтой сэтгэгдлүүд.
     */
    public function reviews(Request $request): JsonResponse
    {
        $reviews = Review::with(['user', 'branch.business'])
            ->where('status', 'flagged')
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => \App\Http\Resources\ReviewResource::collection($reviews->getCollection()),
            'meta' => ['total' => $reviews->total(), 'current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(), 'per_page' => $reviews->perPage()],
        ]);
    }

    /**
     * Хэрэглэгчдийн илгээсэн залруулгууд.
     */
    public function corrections(Request $request): JsonResponse
    {
        $corrections = \App\Models\Correction::with(['user:id,name', 'branch.business:id,name,slug'])
            ->where('status', 'pending')
            ->oldest()
            ->paginate(20);

        return response()->json([
            'data' => $corrections->getCollection()->map(fn ($c) => [
                'id' => $c->id,
                'text' => $c->text,
                'user' => $c->user?->name,
                'branch' => $c->branch ? ($c->branch->business?->name.' — '.$c->branch->name) : null,
                'branch_id' => $c->branch_id,
                'created_at' => $c->created_at,
            ]),
            'meta' => ['total' => $corrections->total(), 'current_page' => $corrections->currentPage(), 'last_page' => $corrections->lastPage(), 'per_page' => $corrections->perPage()],
        ]);
    }

    public function moderateCorrection(Request $request, \App\Models\Correction $correction): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:accept,reject']]);

        $correction->update(['status' => $data['action'] === 'accept' ? 'accepted' : 'rejected']);

        return response()->json(['message' => 'Шийдвэрлэлээ.']);
    }

    public function moderateReview(Request $request, Review $review): JsonResponse
    {
        $data = $request->validate(['action' => ['required', 'in:restore,hide']]);

        // refreshRating() нь Review-ийн saved event-ээр автоматаар дуудагдана
        $review->update(['status' => $data['action'] === 'restore' ? 'active' : 'hidden']);

        return response()->json(['message' => 'Шийдвэрлэлээ.']);
    }
}
