<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Category;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public, read-only listing endpoints (browse, search, detail).
 */
class ListingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'in:newest,rating,views,name'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Listing::query()->active()->with('category');

        if ($slug = $request->query('category')) {
            $category = Category::where('slug', $slug)->first();

            if ($category !== null) {
                $ids = [$category->id, ...$category->children()->pluck('id')->all()];
                $query->whereIn('category_id', $ids);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($term = $request->query('q')) {
            $query->search($term);
        }

        if ($district = $request->query('district')) {
            $query->where('district', $district);
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        // Featured listings always float to the top of results.
        $query->orderByRaw('CASE WHEN featured_until IS NOT NULL AND featured_until > ? THEN 0 ELSE 1 END', [now()]);

        match ($request->query('sort', 'newest')) {
            'rating' => $query->orderByDesc('rating_avg')->orderByDesc('reviews_count'),
            'views' => $query->orderByDesc('views_count'),
            'name' => $query->orderBy('name'),
            default => $query->latest(),
        };

        $listings = $query->paginate($request->integer('per_page', 12))->withQueryString();

        $this->markFavorites($request, $listings->getCollection());

        return ListingResource::collection($listings);
    }

    public function show(Request $request, string $slug): ListingResource
    {
        $listing = Listing::where('slug', $slug)
            ->active()
            ->with(['category', 'images', 'user', 'reviews.user'])
            ->firstOrFail();

        $listing->increment('views_count');

        $this->markFavorites($request, collect([$listing]));

        return new ListingResource($listing);
    }

    public function featured(Request $request): AnonymousResourceCollection
    {
        $listings = Listing::query()
            ->active()
            ->featured()
            ->with('category')
            ->orderByDesc('rating_avg')
            ->limit(8)
            ->get();

        $this->markFavorites($request, $listings);

        return ListingResource::collection($listings);
    }

    /**
     * Annotate is_favorited for the (optionally) authenticated user.
     */
    protected function markFavorites(Request $request, $listings): void
    {
        $user = $request->user('sanctum');

        if ($user === null || $listings->isEmpty()) {
            return;
        }

        $favoriteIds = $user->favorites()
            ->whereIn('listing_id', $listings->pluck('id'))
            ->pluck('listing_id')
            ->flip();

        $listings->each(function (Listing $listing) use ($favoriteIds) {
            $listing->is_favorited = $favoriteIds->has($listing->id);
        });
    }
}
