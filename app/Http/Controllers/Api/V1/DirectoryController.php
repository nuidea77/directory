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
use Illuminate\Support\Facades\Cache;
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
            ->withCount('allBusinesses as businesses_count')
            ->orderBy('sort_order')
            ->get();

        // Нүүрийн онцлох — хот тус бүрт 6 зай, дараа нь тухайн хотын өндөр
        // үнэлгээтэй бизнесүүдээр дүүргэнэ
        $city = (string) $request->query('city', 'Улаанбаатар');
        $slots = (int) config('billing.ads.home_featured.slots', 6);

        $featuredIds = Campaign::query()->running()
            ->where('type', 'home_featured')
            ->where(fn ($q) => $q->where('city', $city)->orWhereNull('city'))
            ->orderBy('slot')
            ->pluck('business_id');

        // Нийтэд зөвхөн ИДЭВХТЭЙ салбарыг үзүүлнэ (хүлээгдэж буй/татгалзсан
        // салбар нийтийн хариултад орж байсан)
        $publicBranches = ['category', 'branches' => fn ($q) => $q->where('status', 'active'), 'branches.images'];

        // Тухайн хотод идэвхтэй салбартай эсэх
        $inCity = fn ($q) => $q->where('status', 'active')->where('city', $city);

        $featured = Business::with($publicBranches)
            ->whereIn('id', $featuredIds)
            ->whereHas('branches', $inCity)
            ->get()
            ->sortBy(fn ($b) => $featuredIds->search($b->id))
            ->values();

        // «Өдөр тутам эргэлдэнэ» — төлбөртэй зарууд өдөр бүр ээлжлэн эхэнд
        // гарна (өмнө нь үргэлж нэг дараалалтай байсан)
        if ($featured->count() > 1) {
            $shift = now()->dayOfYear % $featured->count();
            $featured = $featured->slice($shift)->concat($featured->take($shift))->values();
        }

        if ($featured->count() < $slots) {
            // Бүх бизнесийг PHP рүү татахгүй — эрэмбийг SQL дээр хийнэ.
            // Зөвхөн тухайн хотын бизнесүүд — өмнө нь хот харгалзахгүй
            // дүүргэдэг байсан тул нүүр хуудас хот болгонд ижил байв.
            $fill = Business::with($publicBranches)
                ->whereNotIn('id', $featuredIds)
                ->whereHas('branches', $inCity)
                ->withAvg(['branches as rating_avg_all' => $inCity], 'rating_avg')
                ->withSum(['branches as reviews_total' => $inCity], 'reviews_count')
                ->orderByDesc('rating_avg_all')
                ->orderByDesc('reviews_total')
                ->limit($slots - $featured->count())
                ->get();

            $featured = $featured->concat($fill)->values();
        }

        $featured->each(function (Business $b) use ($featuredIds) {
            $b->is_featured = $featuredIds->contains($b->id);
        });

        $this->markFavorites($request, $featured);

        return response()->json([
            'city' => $city,
            'categories' => CategoryResource::collection($categories),
            'featured' => BusinessResource::collection($featured),
            // Сэдэвчилсэн блокууд (хаана хооллох, болзох, 24 цаг, шинэ)
            'sections' => $this->homeSections($city),
            'stats' => [
                // Идэвхтэй салбартай, өөрөөр хэлбэл хайлтад олдох бизнесүүд
                'businesses' => Business::whereHas('branches', fn ($q) => $q->where('status', 'active'))->count(),
                'branches' => Branch::where('status', 'active')->count(),
                // Тухайн хотод хэдэн бизнес байгаа (хоосон хотод мэдэгдэнэ)
                'city_businesses' => Business::whereHas('branches', $inCity)->count(),
            ],
        ]);
    }

    /**
     * Нүүрийн сэдэвчилсэн блокууд — «Хаана хооллох вэ?», «Болзоход тохиромжтой»
     * гэх мэт. Хот тус бүрт 10 минут cache (агуулга нь өдөрт хэд хэдэн удаа
     * л өөрчлөгддөг), зөвхөн ЦЭВЭР массив хадгална.
     */
    protected function homeSections(string $city): array
    {
        return Cache::remember("home:sections:v2:{$city}", 600, function () use ($city) {
            $ids = fn (string $slug) => optional(Category::where('slug', $slug)->first())->descendantIds() ?? [];

            // Идэвхтэй зартай бизнесүүд — блокуудад «ОНЦЛОХ» тэмдэгтэй гарна
            $adBusinessIds = Campaign::query()->running()->pluck('business_id')->unique()->all();

            $rows = function (callable $tune, int $limit) use ($city, $adBusinessIds) {
                $query = Branch::query()->active()
                    ->where('city', $city)
                    ->whereHas('business')
                    ->with(['business.category', 'images']);

                $tune($query);

                return $query->limit($limit)->get()->map(fn (Branch $b) => [
                    'id' => $b->id,
                    'slug' => $b->business->slug,
                    'name' => $b->business->name,
                    'logo_url' => $b->business->logo_path
                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($b->business->logo_path)
                        : null,
                    'is_verified' => (bool) $b->business->is_verified,
                    'is_featured' => in_array($b->business_id, $adBusinessIds, true),
                    'category' => $b->business->category?->name,
                    'category_slug' => $b->business->category?->slug,
                    'is_open' => $b->openState()['open'],
                    'open_label' => $b->openState()['label'],
                    'district' => $b->district,
                    'address' => $b->address,
                    'price_level' => $b->business->price_level,
                    'rating_avg' => (float) $b->rating_avg,
                    'reviews_count' => (int) $b->reviews_count,
                    'is_24_7' => (bool) $b->is_24_7,
                    'cover_url' => $b->images->first()
                        ? \Illuminate\Support\Facades\Storage::disk('public')->url($b->images->first()->thumb_path ?: $b->images->first()->path)
                        : null,
                ])->all();
            };

            // Жинлэсэн дундаж (Bayesian): цөөн сэтгэгдэлтэй 5.0 нь олон сэтгэгдэлтэй
            // 4.8-аас дээгүүр гарахгүй. Прайор 4.0, жин 5 сэтгэгдэл.
            $byRating = fn ($q) => $q
                ->orderByRaw('(rating_avg * reviews_count + 4.0 * 5) / (reviews_count + 5) desc')
                ->orderByDesc('reviews_count');

            $eatIds = $ids('restaurants');
            $funIds = array_merge($ids('entertainment'), $ids('arts'));

            $sections = [
                [
                    'key' => 'eat',
                    'icon' => 'utensils',
                    'title' => 'Хаана хооллох вэ?',
                    'subtitle' => 'Хамгийн өндөр үнэлгээтэй хоолны газрууд',
                    'link' => ['name' => 'category', 'slug' => 'restaurants'],
                    'items' => $rows(function ($q) use ($eatIds, $byRating) {
                        $q->whereHas('business.categories', fn ($c) => $c->whereIn('categories.id', $eatIds))
                            ->where('reviews_count', '>', 0);
                        $byRating($q);
                    }, 9),
                ],
                [
                    'key' => 'date',
                    'icon' => 'heart',
                    'title' => 'Болзоход тохиромжтой',
                    'subtitle' => 'Уур амьсгалтай ресторан, кафе, зугаа цэнгэл',
                    'link' => ['name' => 'search', 'query' => ['category' => 'restaurants', 'rating' => 4]],
                    'items' => $rows(function ($q) use ($eatIds, $funIds, $byRating) {
                        $q->whereHas('business.categories', fn ($c) => $c->whereIn('categories.id', array_merge($eatIds, $funIds)))
                            ->whereHas('business', fn ($b) => $b->whereIn('price_level', ['₮₮', '₮₮₮']))
                            ->where('rating_avg', '>=', 4);
                        $byRating($q);
                    }, 9),
                ],
                [
                    'key' => 'open_24_7',
                    'icon' => 'clock',
                    'title' => '24 цагаар нээлттэй',
                    'subtitle' => 'Шөнө ч хаалгаа хаадаггүй газрууд',
                    'link' => ['name' => 'search', 'query' => ['open_24_7' => 1]],
                    'items' => $rows(function ($q) use ($byRating) {
                        $q->where('is_24_7', true);
                        $byRating($q);
                    }, 6),
                ],
                [
                    'key' => 'newest',
                    'icon' => 'sparkles',
                    'title' => 'Шинээр нэмэгдсэн',
                    'subtitle' => 'Сүүлд бүртгүүлсэн бизнесүүд',
                    'link' => ['name' => 'search', 'query' => ['sort' => 'new']],
                    'items' => $rows(fn ($q) => $q->orderByDesc('id'), 4),
                ],
            ];

            // «Болзоход тохиромжтой» блок «Хаана хооллох вэ?»-тэй бүрэн давхцвал
            // нүүр хуудас нэг бизнесийг дахин дахин харуулна — давхцлыг арилгана
            // (гэхдээ 2-оос цөөн үлдвэл хэвээр нь орхино)
            $eatIdsShown = array_column($sections[0]['items'] ?? [], 'id');
            $dateItems = array_values(array_filter(
                $sections[1]['items'] ?? [],
                fn ($i) => ! in_array($i['id'], $eatIdsShown, true),
            ));

            if (count($dateItems) >= 2) {
                $sections[1]['items'] = $dateItems;
            }

            // Хоосон блокыг үзүүлэхгүй
            return array_values(array_filter($sections, fn ($s) => count($s['items']) > 0));
        });
    }

    /**
     * Хайлт — салбарын түвшинд (дүүрэг, нээлттэй, үнэлгээ гэх мэт шүүлтүүр).
     * Онцлох (category_featured) кампанит ажилтай бизнесүүд дээр гарна.
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
            'open_24_7' => ['nullable', 'boolean'],
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
            // Бүх түвшний дэд ангилал (эцэг сонговол дэд дэд нь ч илэрнэ)
            $ids = $category->descendantIds();
            // Үндсэн ба нэмэлт ангилал хоёуланг нь хамарна (pivot дотор бүгд бий)
            $query->whereHas('business.categories', fn ($q) => $q->whereIn('categories.id', $ids));
        }

        $term = trim((string) $request->query('q'));

        if ($term !== '') {
            // LIKE-ийн % _ тэмдэгтүүдийг escape хийнэ
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $q) use ($like) {
                $q->whereHas('business', fn ($b) => $b->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('subcategory', 'like', $like))
                    ->orWhere('address', 'like', $like)
                    ->orWhere('name', 'like', $like);
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

        // 24/7 ажилладаг — SQL багана тул хуудаслалтын тоо зөв гарна
        if ($request->boolean('open_24_7')) {
            $query->where('is_24_7', true);
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

        // Онцлох зарууд: тухайн зар нь БИЗНЕСийг бүхэлд нь биш, зорилтот
        // САЛБАРыг нь онцолно (Баянзүрхэд авсан зар Хан-Уулын салбарыг
        // онцлох ёсгүй). branch_id заасан бол зөвхөн тэр салбар.
        $featuredCampaigns = collect();

        if ($category !== null) {
            $district = $request->query('district');

            $featuredCampaigns = $featuredCampaigns->merge(
                Campaign::query()->running()
                    ->where('type', 'category_featured')
                    ->where('category_id', $category->id)
                    // Дүүрэг сонгосон бол тухайн дүүргийн БОЛОН улс даяарын
                    // (district=null) зар онцлогдоно — өөр дүүргийнх орохгүй.
                    // Дүүрэг сонгоогүй (бүх дүүрэг) үед ангиллын идэвхтэй зар
                    // бүгд онцлогдоно — тэд бүгд ямар нэг дүүрэгт ажиллаж байгаа.
                    ->when($district, fn ($q) => $q->where(
                        fn ($w) => $w->where('district', $district)->orWhereNull('district'),
                    ))
                    ->orderBy('slot')
                    ->get(['business_id', 'branch_id', 'district']),
            );
        }

        // Зар бүрийн зорилтот салбаруудыг тодорхойлно
        $featuredBranchIds = collect();

        if ($featuredCampaigns->isNotEmpty()) {
            $featuredBranchIds = Branch::query()->active()
                ->where(function (Builder $q) use ($featuredCampaigns) {
                    foreach ($featuredCampaigns as $c) {
                        $q->orWhere(function (Builder $w) use ($c) {
                            $w->where('business_id', $c->business_id);

                            if ($c->branch_id !== null) {
                                $w->where('id', $c->branch_id);
                            } elseif ($c->district !== null) {
                                $w->where('district', $c->district);
                            }
                        });
                    }
                })
                ->pluck('id');
        }

        if ($featuredBranchIds->isNotEmpty()) {
            $placeholders = $featuredBranchIds->map(fn () => '?')->implode(',');
            $query->orderByRaw("CASE WHEN branches.id IN ({$placeholders}) THEN 0 ELSE 1 END", $featuredBranchIds->all());
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

        // Онцлохыг САЛБАР дээр нь тэмдэглэнэ: eager-load нэг бизнесийн бүх
        // салбарт ижил business объект оноодог тул business дээр тэмдэглэвэл
        // сүүлийн салбар өмнөхийг дарж, онцлох тэмдэг алга болдог байсан
        $branches->getCollection()->each(function (Branch $b) use ($featuredBranchIds) {
            $b->is_featured = $featuredBranchIds->contains($b->id);
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
                'categories',
                'branches' => fn ($q) => $q->where('status', 'active')
                    ->with(['images', 'reviews' => fn ($r) => $r->where('status', 'active')->with('user')]),
            ])
            ->firstOrFail();

        abort_if($business->branches->isEmpty(), 404);

        $this->markFavorites($request, collect([$business]));

        // Ижил төрлийн бизнесүүд — зөвхөн идэвхтэй салбар, зурагтайгаа
        // (images-гүй ачаалбал cover_url хоосон гарч, N+1 үүсдэг)
        $similarCategoryIds = $business->categories->pluck('id')->push($business->category_id)->unique()->all();

        $similar = Business::whereHas('categories', fn ($q) => $q->whereIn('categories.id', $similarCategoryIds))
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
