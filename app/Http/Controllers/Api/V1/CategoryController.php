<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Branch;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        // Ангиллын мод бараг өөрчлөгддөггүй — 10 минут cache.
        // Cache-д зөвхөн ЦЭВЭР массив хадгална: Eloquent/Resource объект
        // хадгалбал unserialize эвдэрч (__PHP_Incomplete_Class) 500 өгдөг.
        $payload = Cache::remember('categories:index:v3', 600, function () {
            $all = Category::query()->withCount('businesses')->orderBy('sort_order')->get();

            $byParent = $all->groupBy(fn (Category $c) => $c->parent_id ?? 0);

            // Гүн бүрт рекурсиваар мод угсарна (үндсэн → дэд → дэд дэд …).
            // Мөн салбар бүрийн нийт тоо (өөрийн + үр удмын) тооцно.
            $build = function (int $parentId, int $depth) use (&$build, $byParent): array {
                return ($byParent[$parentId] ?? collect())->map(function (Category $c) use (&$build, $depth) {
                    $children = $build($c->id, $depth + 1);

                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                        'slug' => $c->slug,
                        'description' => $c->description,
                        'icon' => $c->icon,
                        'parent_id' => $c->parent_id,
                        'depth' => $depth,
                        'businesses_count' => (int) $c->businesses_count,
                        // Дэд ангиллуудынхыг оруулсан нийт тоо
                        'businesses_total' => (int) $c->businesses_count + array_sum(array_column($children, 'businesses_total')),
                        'children' => $children,
                    ];
                })->values()->all();
            };

            return $build(0, 1);
        });

        return response()->json(['data' => $payload]);
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->withCount('businesses')
            // Одоогийн ангиллаас 2 түвшин доош (дэд + дэд дэд) — chip-үүдэд
            ->with(['children' => fn ($q) => $q->withCount('businesses')
                ->with(['children' => fn ($q2) => $q2->withCount('businesses')])])
            ->firstOrFail();

        // Ангиллын статистик: нийт, баталгаажсан, одоо нээлттэй.
        // Бүх ТҮВШНИЙ дэд ангиллыг оруулна — жагсаалт (search) тэднийг
        // харуулдаг тул статистик нь илэрцийн тоотой зөрдөг байсан.
        $categoryIds = $category->descendantIds();

        $branchIds = Branch::query()->active()
            ->whereHas('business', fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->pluck('id');

        $verified = Branch::whereIn('id', $branchIds)
            ->whereHas('business', fn ($q) => $q->where('is_verified', true))
            ->count();

        // Санах ойд бүгдийг ачаалахгүй — эхний 500-аар хязгаарлана
        $openNow = Branch::whereIn('id', $branchIds)->limit(500)->get(['id', 'hours'])
            ->filter(fn (Branch $b) => $b->openState()['open'])
            ->count();

        return response()->json([
            'data' => new CategoryResource($category),
            // Breadcrumb: үндсэн → … → одоогийн
            'ancestors' => $category->ancestors(),
            'stats' => [
                'total' => $branchIds->count(),
                'verified' => $verified,
                'open_now' => $openNow,
            ],
        ]);
    }
}
