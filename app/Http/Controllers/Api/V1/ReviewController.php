<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * Create or update the authenticated user's review on a listing.
     */
    public function store(Request $request, Listing $listing): ReviewResource
    {
        abort_unless($listing->status === 'active', 404);

        if ($listing->user_id === $request->user()->id) {
            throw ValidationException::withMessages([
                'rating' => 'Өөрийн байгууллагад үнэлгээ өгөх боломжгүй.',
            ]);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $listing->reviews()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return new ReviewResource($review->load('user'));
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        $review = $listing->reviews()->where('user_id', $request->user()->id)->firstOrFail();
        $review->delete();

        return response()->json(['message' => 'Үнэлгээ устгагдлаа.']);
    }
}
