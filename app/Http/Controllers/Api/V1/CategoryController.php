<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::whereNull('parent_id')
            ->withCount(['listings' => fn ($q) => $q->where('status', 'active')])
            ->with('children')
            ->orderBy('sort_order')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function show(string $slug): CategoryResource
    {
        $category = Category::where('slug', $slug)
            ->withCount(['listings' => fn ($q) => $q->where('status', 'active')])
            ->with('children')
            ->firstOrFail();

        return new CategoryResource($category);
    }
}
