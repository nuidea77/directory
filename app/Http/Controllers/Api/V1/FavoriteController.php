<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavoriteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $listings = Listing::query()
            ->active()
            ->whereIn('id', $request->user()->favorites()->pluck('listing_id'))
            ->with('category')
            ->latest()
            ->paginate(20);

        $listings->getCollection()->each(fn (Listing $l) => $l->is_favorited = true);

        return ListingResource::collection($listings);
    }

    public function toggle(Request $request, Listing $listing): JsonResponse
    {
        abort_unless($listing->status === 'active', 404);

        $existing = $request->user()->favorites()->where('listing_id', $listing->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return response()->json(['favorited' => false]);
        }

        $request->user()->favorites()->create(['listing_id' => $listing->id]);

        return response()->json(['favorited' => true]);
    }
}
