<?php

namespace App\Http\Controllers\Api\V1\Console;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use App\Models\Organization;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Бизнес талын сэтгэгдэл: жагсаах, хариу бичих.
 */
class ConsoleReviewController extends Controller
{
    public function index(Request $request, Organization $organization): AnonymousResourceCollection
    {
        abort_unless($organization->owner_id === $request->user()->id, 403);

        $branchIds = $organization->businesses()->with('branches:id,business_id')->get()
            ->flatMap(fn ($b) => $b->branches->pluck('id'));

        $reviews = Review::whereIn('branch_id', $branchIds)
            ->with(['user', 'branch.business'])
            ->when($request->boolean('unreplied'), fn ($q) => $q->whereNull('reply'))
            ->latest()
            ->paginate(20);

        return ReviewResource::collection($reviews);
    }

    public function reply(Request $request, Review $review): ReviewResource
    {
        abort_unless($review->branch->business->organization->owner_id === $request->user()->id, 403);

        $data = $request->validate(['reply' => ['required', 'string', 'max:2000']]);

        $review->update(['reply' => $data['reply'], 'replied_at' => now()]);

        return new ReviewResource($review->refresh()->load(['user', 'branch.business']));
    }
}
