<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Authenticated listing management: the owner's own listings.
 */
class MyListingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $listings = $request->user()->listings()
            ->with('category')
            ->latest()
            ->paginate(20);

        return ListingResource::collection($listings);
    }

    public function store(Request $request): ListingResource
    {
        $data = $this->validateListing($request);

        $listing = $request->user()->listings()->create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            'status' => 'active',
        ]);

        $this->storeUploads($request, $listing);

        return new ListingResource($listing->refresh()->load(['category', 'images']));
    }

    public function show(Request $request, Listing $listing): ListingResource
    {
        $this->authorizeOwner($request, $listing);

        return new ListingResource($listing->load(['category', 'images']));
    }

    public function update(Request $request, Listing $listing): ListingResource
    {
        $this->authorizeOwner($request, $listing);

        $data = $this->validateListing($request, updating: true);

        if (array_key_exists('name', $data) && $data['name'] !== $listing->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $listing->id);
        }

        $listing->update($data);
        $this->storeUploads($request, $listing);

        return new ListingResource($listing->refresh()->load(['category', 'images']));
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        $this->authorizeOwner($request, $listing);

        $paths = array_filter([
            $listing->logo_path,
            $listing->cover_path,
            ...$listing->images->pluck('path')->all(),
        ]);

        $listing->delete();
        Storage::disk('public')->delete($paths);

        return response()->json(['message' => 'Бүртгэл устгагдлаа.']);
    }

    public function addImages(Request $request, Listing $listing): ListingResource
    {
        $this->authorizeOwner($request, $listing);

        $request->validate([
            'images' => ['required', 'array', 'max:6'],
            'images.*' => ['image', 'max:4096'],
        ]);

        $existing = $listing->images()->count();

        if ($existing + count($request->file('images', [])) > 10) {
            abort(422, 'Нэг бүртгэлд дээд тал нь 10 зураг оруулах боломжтой.');
        }

        foreach ($request->file('images', []) as $i => $file) {
            $listing->images()->create([
                'path' => $file->store('listings/gallery', 'public'),
                'sort_order' => $existing + $i,
            ]);
        }

        return new ListingResource($listing->load(['category', 'images']));
    }

    public function deleteImage(Request $request, Listing $listing, ListingImage $image): JsonResponse
    {
        $this->authorizeOwner($request, $listing);

        abort_unless($image->listing_id === $listing->id, 404);

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return response()->json(['message' => 'Зураг устгагдлаа.']);
    }

    protected function validateListing(Request $request, bool $updating = false): array
    {
        $required = $updating ? 'sometimes' : 'required';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:150'],
            'category_id' => [$required, 'integer', 'exists:categories,id'],
            'phone' => [$required, 'string', 'regex:/^[0-9]{6,11}$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'url', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'opening_hours' => ['nullable', 'array'],
            'socials' => ['nullable', 'array'],
            'socials.facebook' => ['nullable', 'url'],
            'socials.instagram' => ['nullable', 'url'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ]);

        unset($data['logo'], $data['cover']);

        return $data;
    }

    protected function storeUploads(Request $request, Listing $listing): void
    {
        $updates = [];

        if ($request->hasFile('logo')) {
            if ($listing->logo_path) {
                Storage::disk('public')->delete($listing->logo_path);
            }
            $updates['logo_path'] = $request->file('logo')->store('listings/logos', 'public');
        }

        if ($request->hasFile('cover')) {
            if ($listing->cover_path) {
                Storage::disk('public')->delete($listing->cover_path);
            }
            $updates['cover_path'] = $request->file('cover')->store('listings/covers', 'public');
        }

        if ($updates !== []) {
            $listing->update($updates);
        }
    }

    protected function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'listing';
        $slug = $base;
        $n = 1;

        while (Listing::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }

    protected function authorizeOwner(Request $request, Listing $listing): void
    {
        abort_unless($listing->user_id === $request->user()->id, 403, 'Энэ бүртгэлийг удирдах эрх байхгүй.');
    }
}
