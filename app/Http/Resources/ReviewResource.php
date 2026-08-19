<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'reply' => $this->reply,
            'replied_at' => $this->replied_at,
            'status' => $this->status,
            'helpful_count' => $this->helpful_count,
            'user' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'reviews_count' => $this->when($this->user?->relationLoaded('reviews') || isset($this->user?->reviews_count), fn () => $this->user->reviews_count),
            ],
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'business_name' => $this->branch->business?->name,
                'business_slug' => $this->branch->business?->slug,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
