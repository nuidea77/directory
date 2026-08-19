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
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'branch' => $this->whenLoaded('branch', fn () => [
                'id' => $this->branch->id,
                'name' => $this->branch->name,
                'business_name' => $this->branch->relationLoaded('business') ? $this->branch->business?->name : null,
                'business_slug' => $this->branch->relationLoaded('business') ? $this->branch->business?->slug : null,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
