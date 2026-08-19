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
     * Засаг захиргааны нэгжүүд: нийслэл + 21 аймаг, дүүрэг/сумдтайгаа
     * (шүүлтүүр, хаягийн сонголтод ашиглана).
     */
    public function locations(): JsonResponse
    {
        return response()->json([
            'data' => collect(config('locations'))
                ->map(fn (array $districts, string $city) => ['city' => $city, 'districts' => $districts])
                ->values(),
            'amenities' => config('amenities'),
        ]);
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

        // Нүүрийн онцлох — 6 зай (хот тус бүрт), дараа нь өндөр үнэлгээтэйгээр дүүргэнэ
        $city = (string) $request->query('city', 'Улаанбаатар');
        $featuredIds = Campaign::query()->running()
            ->where('type', 'home_featured')
            ->where(fn ($q) => $q->where('city', $city)->orWhereNull('city'))
            ->orderBy('slot')
            ->pluck('business_id');

        // Нийтэд зөвхөн ИДЭВХТЭЙ салбарыг үзүүлнэ (хүлээгдэж буй/татгалзсан
        // салбар нийтийн хариултад орж байсан)
        $publicBranches = ['category', 'branches' => fn ($q) => $q->where('status', 'active'), 'branches.images'];

        $featured = Business::with($publicBranches)
            ->whereIn('id', $featuredIds)
            ->whereHas('branches', fn ($q) => $q->where('status', 'active'))
            ->get()
            ->sortBy(fn ($b) => $featuredIds->search($b->id))
            ->values();

        if ($featured->count() < 6) {
            // Бүх бизнесийг PHP рүү татахгүй — эрэмбийг SQL дээр хийнэ
            $fill = Business::with($publicBranches)
                ->whereNotIn('id', $featuredIds)
                ->whereHas('branches', fn ($q) => $q->where('status', 'active'))
                ->withAvg(['branches as rating_avg_all' => fn ($q) => $q->where('status', 'active')], 'rating_avg')
                ->withSum(['branches as reviews_total' => fn ($q) => $q->where('status', 'active')], 'reviews_count')
                ->orderByDesc('rating_avg_all')
                ->orderByDesc('reviews_total')
                ->limit(6 - $featured->count())
                ->get();

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
                // Идэвхтэй салбартай, өөрөөр хэлбэл хайлтад олдох бизнесүүд
                'businesses' => Business::whereHas('branches', fn ($q) => $q->where('status', 'active'))->count(),
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
            'city' => ['nullable', 'string', 'max:100'],
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

        // Байхгүй ангилал дуудвал шүүлтүүр чимээгүй алгасагдаж БҮХ бизнес
        // буцаадаг байсан — одоо хоосон илэрц буцаана
        if ($request->query('category') && $category === null) {
            return response()->json([
                'data' => [],
                'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'per_page' => $request->integer('per_page', 20)],
            ]);
        }

        $query = Branch::query()->active()
            ->with(['business.category', 'images'])
            ->whereHas('business');

        if ($category !== null) {
            $ids = [$category->id, ...$category->children()->pluck('id')->all()];
            $query->whereHas('business', fn ($q) => $q->whereIn('category_id', $ids));
        }

        $term = trim((string) $request->query('q'));

        // Түлхүүр үгийн зар: худалдаж авсан үг хайлтын үгэнд агуулагдах эсвэл
        // эсрэгээрээ бол тухайн бизнес текст тохироогүй ч илэрцэд орж дээр гарна
        $keywordFeaturedIds = collect();

        if ($term !== '') {
            $needle = mb_strtolower($term);
            $keywordFeaturedIds = Campaign::query()->running()
                ->where('type', 'keyword')
                ->orderBy('slot')
                ->get(['business_id', 'keyword'])
                ->filter(fn ($c) => $c->keyword !== null
                    && (str_contains($needle, $c->keyword) || str_contains($c->keyword, $needle)))
                ->pluck('business_id');
        }

        if ($term !== '') {
            // LIKE-ийн % _ тэмдэгтүүдийг escape хийнэ
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $q) use ($like, $keywordFeaturedIds) {
                $q->where(function (Builder $qq) use ($like) {
                    $qq->whereHas('business', fn ($b) => $b->where('name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('subcategory', 'like', $like))
                        ->orWhere('address', 'like', $like)
                        ->orWhere('name', 'like', $like);
                });

                if ($keywordFeaturedIds->isNotEmpty()) {
                    $q->orWhereIn('business_id', $keywordFeaturedIds);
                }
            });
        }

        if ($city = $request->query('city')) {
            $query->where('city', $city);
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
            // MySQL + SQLite хоёуланд ажиллах хэлбэр: min()/LEAST-ийн оронд CASE,
            // параметрийн тоон харьцуулалтад "? + 0.0" (string bind-ийг тоо болгоно)
            $inner = 'cos(radians(?)) * cos(radians(lat)) * cos(radians(lng) - radians(?)) + sin(radians(?)) * sin(radians(lat))';
            $clamped = "CASE WHEN {$inner} > 1.0 THEN 1.0 WHEN {$inner} < -1.0 THEN -1.0 ELSE {$inner} END";
            $haversine = "(6371 * acos({$clamped}))";
            $bind = [$lat, $lng, $lat, $lat, $lng, $lat, $lat, $lng, $lat];

            $query->whereNotNull('lat')
                ->selectRaw("branches.*, {$haversine} as distance_km", $bind)
                ->whereRaw("{$haversine} <= (? + 0.0)", [...$bind, $radius]);
        }

        // Онцлох бизнесүүд дээр гарна
        $featuredBusinessIds = collect();

        if ($category !== null) {
            $district = $request->query('district');

            $featuredBusinessIds = $featuredBusinessIds->merge(
                Campaign::query()->running()
                    ->where('type', 'category_featured')
                    ->where('category_id', $category->id)
                    // Дүүрэг сонгосон бол тухайн дүүргийн БОЛОН улс даяарын
                    // (district=null) зар хоёулаа онцлогдоно. Дүүрэг сонгоогүй
                    // үед зөвхөн улс даяарынх — эс бөгөөс нэг дүүргийн зар
                    // бүх дүүрэгт дээгүүр гарна.
                    ->where(fn ($q) => $district
                        ? $q->where('district', $district)->orWhereNull('district')
                        : $q->whereNull('district'))
                    ->orderBy('slot')
                    ->pluck('business_id'),
            );
        }

        $featuredBusinessIds = $featuredBusinessIds->merge($keywordFeaturedIds);

        $featuredBusinessIds = $featuredBusinessIds->unique()->values();

        if ($featuredBusinessIds->isNotEmpty()) {
            $placeholders = $featuredBusinessIds->map(fn () => '?')->implode(',');
            $query->orderByRaw("CASE WHEN business_id IN ({$placeholders}) THEN 0 ELSE 1 END", $featuredBusinessIds->all());
        }

        // Бизнес эрхийн «ТОП жагсаалт» — идэвхтэй business эрхтэй байгууллагууд
        // онцлохын дараа, бусдын өмнө эрэмбэлэгдэнэ
        $query->orderByRaw(
            'CASE WHEN business_id IN (select id from businesses where organization_id in '
            .'(select id from organizations where plan = ? and plan_expires_at > ?)) THEN 0 ELSE 1 END',
            ['business', now()],
        );

        match ($request->query('sort', 'rating')) {
            'newest' => $query->latest('branches.created_at'),
            'reviews' => $query->orderByDesc('reviews_count'),
            // distance_km багана зөвхөн lat БА lng хоёул ирсэн үед л SELECT-д
            // нэмэгддэг — зөвхөн lat-аар эрэмбэлэх гэвэл MySQL дээр 500 өгдөг
            'distance' => ($lat !== null && $lng !== null) ? $query->orderBy('distance_km') : $query->orderByDesc('rating_avg'),
            default => $query->orderByDesc('rating_avg')->orderByDesc('reviews_count'),
        };

        $perPage = $request->integer('per_page', 20);

        if ($request->boolean('open_now')) {
            // Цагийн шүүлтүүрийг pagination-аас ӨМНӨ тавьж meta-г зөв гаргана
            $all = $query->limit(500)->get()
                ->filter(fn (Branch $b) => $b->openState()['open'])
                ->values();
            $page = max(1, $request->integer('page', 1));
            $branches = new \Illuminate\Pagination\LengthAwarePaginator(
                $all->forPage($page, $perPage)->values(),
                $all->count(),
                $perPage,
                $page,
            );
        } else {
            $branches = $query->paginate($perPage)->withQueryString();
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
                'branches' => fn ($q) => $q->where('status', 'active')
                    ->with(['images', 'reviews' => fn ($r) => $r->where('status', 'active')->with('user')]),
            ])
            ->firstOrFail();

        abort_if($business->branches->isEmpty(), 404);

        $this->markFavorites($request, collect([$business]));

        // Ижил төрлийн бизнесүүд — зөвхөн идэвхтэй салбар, зурагтайгаа
        // (images-гүй ачаалбал cover_url хоосон гарч, N+1 үүсдэг)
        $similar = Business::where('category_id', $business->category_id)
            ->where('id', '!=', $business->id)
            ->with([
                'category',
                'branches' => fn ($q) => $q->where('status', 'active')->with('images'),
            ])
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

        // Зөвхөн идэвхтэй салбарын статистикийг тоолно (fraud/noise бууруулна)
        if ($branch->status === 'active') {
            $branch->recordEvent($data['type'], $data['source'] ?? null);
        }

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
