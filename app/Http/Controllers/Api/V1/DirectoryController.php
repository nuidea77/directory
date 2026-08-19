<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Http\Resources\BusinessResource;
use App\Http\Resources\CategoryResource;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Campaign;
use App\Models\Category;
use App\Services\Billing\CampaignService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Нийтийн лавлах: нүүр, хайлт, бизнесийн дэлгэрэнгүй, ойролцоох.
 */
class DirectoryController extends Controller
{
    public function __construct(protected CampaignService $campaigns)
    {
    }

    /**
     * Нүүр хуудасны багц өгөгдөл.
     */
    public function home(Request $request): JsonResponse
    {
        $this->campaigns->sync();

        $categories = Category::whereNull('parent_id')
            ->withCount('businesses')
            ->orderBy('sort_order')
            ->get();

        // Нүүрийн онцлох — 6 зай, дараа нь өндөр үнэлгээтэйгээр дүүргэнэ
        $featuredIds = Campaign::query()->running()
            ->where('type', 'home_featured')
            ->orderBy('slot')
            ->pluck('business_id');

        $featured = Business::with(['category', 'branches.images'])
            ->whereIn('id', $featuredIds)
            ->get()
            ->sortBy(fn ($b) => $featuredIds->search($b->id))
            ->values();

        if ($featured->count() < 6) {
            $fill = Business::with(['category', 'branches.images'])
                ->whereNotIn('id', $featuredIds)
                ->whereHas('branches', fn ($q) => $q->where('status', 'active'))
                ->get()
                ->sortByDesc(fn (Business $b) => $b->ratingAvg() * 1000 + $b->reviewsTotal())
                ->take(6 - $featured->count());

            $featured = $featured->concat($fill)->values();
        }

        $featured->each(function (Business $b) use ($featuredIds) {
            $b->is_featured = $featuredIds->contains($b->id);
        });

        $this->markFavorites($request, $featured);

        return response()->json([
            'categories' => CategoryResource::collection($categories),
            'featured' => BusinessResource::collection($featured),
            'stats' => [
                'businesses' => Business::count(),
                'branches' => Branch::where('status', 'active')->count(),
            ],
        ]);
    }

    /**
     * Хайлт — салбарын түвшинд (дүүрэг, нээлттэй, үнэлгээ гэх мэт шүүлтүүр).
     * Онцлох (category_featured / keyword) кампанит ажилтай бизнесүүд дээр гарна.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', 'in:₮,₮₮,₮₮₮'],
            'rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'open_now' => ['nullable', 'boolean'],
            'verified' => ['nullable', 'boolean'],
            'amenity' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', 'in:rating,newest,reviews,distance'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'radius' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $this->campaigns->sync();

        $category = $request->query('category')
            ? Category::where('slug', $request->query('category'))->first()
            : null;

        $query = Branch::query()->active()
            ->with(['business.category', 'images'])
            ->whereHas('business');

        if ($category !== null) {
            $ids = [$category->id, ...$category->children()->pluck('id')->all()];
            $query->whereHas('business', fn ($q) => $q->whereIn('category_id', $ids));
        }

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function (Builder $q) use ($term) {
                $q->whereHas('business', fn ($b) => $b->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%")
                    ->orWhere('subcategory', 'like', "%{$term}%"))
                    ->orWhere('address', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%");
            });
        }

        if ($district = $request->query('district')) {
            $query->where('district', $district);
        }

        if ($price = $request->query('price')) {
            $query->whereHas('business', fn ($q) => $q->where('price_level', $price));
        }

        if ($rating = $request->query('rating')) {
            $query->where('rating_avg', '>=', (float) $rating);
        }

        if ($request->boolean('verified')) {
            $query->whereHas('business', fn ($q) => $q->where('is_verified', true));
        }

        if ($amenity = $request->query('amenity')) {
            $query->whereJsonContains('amenities', $amenity);
        }

        // Зайн хайлт (haversine, км)
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        if ($lat !== null && $lng !== null) {
            $radius = (float) $request->query('radius', 5);
            $haversine = '(6371 * acos(min(1.0, cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat)))))';
            $query->whereNotNull('lat')
                ->selectRaw("branches.*, {$haversine} as distance_km", [$lat, $lng, $lat])
                // CAST — SQLite тоо ба текстийг шууд харьцуулахад төрлөөр эрэмбэлдэг
                ->whereRaw("{$haversine} <= CAST(? AS REAL)", [$lat, $lng, $lat, $radius]);
        }

        // Онцлох бизнесүүд дээр гарна
        $featuredBusinessIds = collect();

        if ($category !== null) {
            $featuredBusinessIds = $featuredBusinessIds->merge(
                Campaign::query()->running()
                    ->where('type', 'category_featured')
                    ->where('category_id', $category->id)
                    ->when($request->query('district'), fn ($q, $d) => $q->where('district', $d))
                    ->orderBy('slot')
                    ->pluck('business_id'),
            );
        }

        if ($term !== '' && $term !== null) {
            $featuredBusinessIds = $featuredBusinessIds->merge(
                Campaign::query()->running()
                    ->where('type', 'keyword')
                    ->where('keyword', mb_strtolower($term))
                    ->orderBy('slot')
                    ->pluck('business_id'),
            );
        }

        $featuredBusinessIds = $featuredBusinessIds->unique()->values();

        if ($featuredBusinessIds->isNotEmpty()) {
            $placeholders = $featuredBusinessIds->map(fn () => '?')->implode(',');
            $query->orderByRaw("CASE WHEN business_id IN ({$placeholders}) THEN 0 ELSE 1 END", $featuredBusinessIds->all());
        }

        match ($request->query('sort', 'rating')) {
            'newest' => $query->latest('branches.created_at'),
            'reviews' => $query->orderByDesc('reviews_count'),
            'distance' => $lat !== null ? $query->orderBy('distance_km') : $query->orderByDesc('rating_avg'),
            default => $query->orderByDesc('rating_avg')->orderByDesc('reviews_count'),
        };

        $branches = $query->paginate($request->integer('per_page', 20))->withQueryString();

        $openNow = $request->boolean('open_now');

        if ($openNow) {
            // Цагийн шүүлтүүрийг PHP талд (жижиг хуудсан дээр)
            $filtered = $branches->getCollection()->filter(fn (Branch $b) => $b->openState()['open'])->values();
            $branches->setCollection($filtered);
        }

        $branches->getCollection()->each(function (Branch $b) use ($featuredBusinessIds) {
            $b->business->is_featured = $featuredBusinessIds->contains($b->business_id);
        });

        return response()->json([
            'data' => BranchResource::collection($branches->getCollection()),
            'meta' => [
                'current_page' => $branches->currentPage(),
                'last_page' => $branches->lastPage(),
                'total' => $branches->total(),
                'per_page' => $branches->perPage(),
            ],
        ]);
    }

    /**
     * Бизнесийн дэлгэрэнгүй — салбар сонгогчтой.
     */
    public function business(Request $request, string $slug): JsonResponse
    {
        $business = Business::where('slug', $slug)
            ->with([
                'category',
                'branches' => fn ($q) => $q->where('status', 'active')->with(['images', 'reviews.user']),
            ])
            ->firstOrFail();

        abort_if($business->branches->isEmpty(), 404);

        $this->markFavorites($request, collect([$business]));

        // Ижил төрлийн бизнесүүд
        $similar = Business::where('category_id', $business->category_id)
            ->where('id', '!=', $business->id)
            ->with(['category', 'branches'])
            ->whereHas('branches', fn ($q) => $q->where('status', 'active'))
            ->limit(3)
            ->get();

        return response()->json([
            'data' => new BusinessResource($business),
            'similar' => BusinessResource::collection($similar),
        ]);
    }

    /**
     * Үзэлт/залгалт/зам заалт бүртгэх.
     */
    public function event(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:view,call,direction'],
            'source' => ['nullable', 'in:category,search,map,direct'],
        ]);

        $branch->recordEvent($data['type'], $data['source'] ?? null);

        return response()->json(['ok' => true]);
    }

    protected function markFavorites(Request $request, $businesses): void
    {
        $user = $request->user('sanctum');

        if ($user === null || $businesses->isEmpty()) {
            return;
        }

        $ids = $user->favorites()->whereIn('business_id', $businesses->pluck('id'))->pluck('business_id')->flip();

        $businesses->each(function (Business $b) use ($ids) {
            $b->is_favorited = $ids->has($b->id);
        });
    }
}
