<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ListingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $disk = Storage::disk('public');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'district' => $this->district,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'logo_url' => $this->logo_path ? $disk->url($this->logo_path) : null,
            'cover_url' => $this->cover_path ? $disk->url($this->cover_path) : null,
            'opening_hours' => $this->opening_hours,
            'socials' => $this->socials,
            'status' => $this->status,
            'is_featured' => $this->isFeatured(),
            'featured_until' => $this->featured_until,
            'views_count' => $this->views_count,
            'rating_avg' => (float) $this->rating_avg,
            'reviews_count' => $this->reviews_count,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($img) => [
                'id' => $img->id,
                'url' => $disk->url($img->path),
                'sort_order' => $img->sort_order,
            ])),
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
            'owner' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'is_favorited' => $this->when(isset($this->is_favorited), fn () => (bool) $this->is_favorited),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
