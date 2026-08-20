<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Branch;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        // Ангиллын мод бараг өөрчлөгддөггүй — 10 минут cache.
        // Cache-д зөвхөн ЦЭВЭР массив хадгална: Eloquent/Resource объект
        // хадгалбал unserialize эвдэрч (__PHP_Incomplete_Class) 500 өгдөг.
        $toRow = fn (Category $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'description' => $c->description,
            'icon' => $c->icon,
            'parent_id' => $c->parent_id,
            'businesses_count' => (int) $c->businesses_count,
        ];

        $payload = \Illuminate\Support\Facades\Cache::remember('categories:index:v2', 600, fn () => Category::whereNull('parent_id')
            ->withCount('businesses')
            ->with(['children' => fn ($q) => $q->withCount('businesses')])
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Category $c) => $toRow($c) + ['children' => $c->children->map($toRow)->all()])
            ->all());

        return response()->json(['data' => $payload]);
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->withCount('businesses')
            ->with(['children' => fn ($q) => $q->withCount('businesses')])
            ->firstOrFail();

        // Ангиллын статистик: нийт, баталгаажсан, одоо нээлттэй.
        // Дэд ангиллуудыг мөн оруулна — жагсаалт (search) тэднийг харуулдаг
        // тул статистик нь илэрцийн тоотой зөрдөг байсан.
        $categoryIds = [$category->id, ...$category->children->pluck('id')->all()];

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
            'stats' => [
                'total' => $branchIds->count(),
                'verified' => $verified,
                'open_now' => $openNow,
            ],
        ]);
    }
}
