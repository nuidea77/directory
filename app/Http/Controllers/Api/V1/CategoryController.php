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
        $categories = Category::whereNull('parent_id')
            ->withCount('businesses')
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => CategoryResource::collection($categories)]);
    }

    public function show(string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)
            ->withCount('businesses')
            ->with(['children' => fn ($q) => $q->withCount('businesses')])
            ->firstOrFail();

        // Ангиллын статистик: нийт, баталгаажсан, одоо нээлттэй
        $branchIds = Branch::query()->active()
            ->whereHas('business', fn ($q) => $q->where('category_id', $category->id))
            ->pluck('id');

        $verified = Branch::whereIn('id', $branchIds)
            ->whereHas('business', fn ($q) => $q->where('is_verified', true))
            ->count();

        $openNow = Branch::whereIn('id', $branchIds)->get()
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
