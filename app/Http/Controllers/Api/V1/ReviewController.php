<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Branch;
use App\Notifications\NewReview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    /**
     * Сэтгэгдэл бичих/шинэчлэх — салбар тус бүрт (өөрийн үйлчлүүлсэн салбарт).
     */
    public function store(Request $request, Branch $branch): ReviewResource
    {
        abort_unless($branch->status === 'active', 404);

        if ($branch->business->organization->owner_id === $request->user()->id) {
            throw ValidationException::withMessages([
                'rating' => 'Өөрийн бизнест үнэлгээ өгөх боломжгүй.',
            ]);
        }

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = $branch->reviews()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        // Шинэ сэтгэгдэл ирэхэд эзэнд мэдэгдэнэ (засварт биш)
        if ($review->wasRecentlyCreated) {
            $branch->business->organization->owner?->notify(new NewReview($review));
        }

        return new ReviewResource($review->load('user'));
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        $review = $branch->reviews()->where('user_id', $request->user()->id)->firstOrFail();
        $review->delete();

        return response()->json(['message' => 'Сэтгэгдэл устгагдлаа.']);
    }

    /**
     * Миний бичсэн сэтгэгдлүүд (хэрэглэгчийн дашбоард).
     */
    public function mine(Request $request): AnonymousResourceCollection
    {
        $reviews = $request->user()->reviews()
            ->with(['branch.business'])
            ->latest()
            ->paginate(20);

        return ReviewResource::collection($reviews);
    }
}
