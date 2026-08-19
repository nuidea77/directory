<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FavoriteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $favorites = $request->user()->favorites()->with('business.category', 'business.branches.images')->latest()->get();

        $businesses = $favorites->map(function ($favorite) {
            $business = $favorite->business;
            $business->is_favorited = true;
            $business->list_name = $favorite->list_name;

            return $business;
        })->filter()->values();

        return BusinessResource::collection($businesses)->additional([
            'lists' => $favorites->pluck('list_name')->filter()->unique()->values(),
        ]);
    }

    public function toggle(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate(['list_name' => ['nullable', 'string', 'max:60']]);

        $existing = $request->user()->favorites()->where('business_id', $business->id)->first();

        if ($existing !== null && ! array_key_exists('list_name', $data)) {
            $existing->delete();

            return response()->json(['favorited' => false]);
        }

        if ($existing !== null) {
            $existing->update(['list_name' => $data['list_name'] ?? null]);

            return response()->json(['favorited' => true]);
        }

        $request->user()->favorites()->create([
            'business_id' => $business->id,
            'list_name' => $data['list_name'] ?? null,
        ]);

        return response()->json(['favorited' => true]);
    }
}
