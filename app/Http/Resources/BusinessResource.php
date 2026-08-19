<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subcategory' => $this->subcategory,
            'description' => $this->description,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'website' => $this->website,
            'facebook' => $this->facebook,
            'instagram' => $this->instagram,
            'price_level' => $this->price_level,
            'is_verified' => $this->is_verified,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'branches' => BranchResource::collection($this->whenLoaded('branches')),
            'rating_avg' => $this->when($this->relationLoaded('branches'), fn () => $this->ratingAvg()),
            'reviews_total' => $this->when($this->relationLoaded('branches'), fn () => $this->reviewsTotal()),
            'branches_count' => $this->when($this->relationLoaded('branches'), fn () => $this->branches->where('status', 'active')->count()),
            'is_favorited' => $this->when(isset($this->is_favorited), fn () => (bool) $this->is_favorited),
            'is_featured' => $this->when(isset($this->is_featured), fn () => (bool) $this->is_featured),
            'organization_id' => $this->organization_id,
            'created_at' => $this->created_at,
        ];
    }
}
