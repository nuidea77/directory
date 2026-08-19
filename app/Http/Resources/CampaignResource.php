<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_name' => config("billing.ads.{$this->type}.name"),
            'business' => $this->whenLoaded('business', fn () => [
                'id' => $this->business->id,
                'name' => $this->business->name,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'district' => $this->district,
            'city' => $this->city,
            'keyword' => $this->keyword,
            'slot' => $this->slot,
            'days' => $this->days,
            'price' => $this->price,
            'status' => $this->status,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'days_left' => $this->ends_at !== null && $this->ends_at->isFuture() ? (int) ceil(now()->diffInDays($this->ends_at, true)) : 0,
            'views_count' => $this->views_count,
            'calls_count' => $this->calls_count,
            'created_at' => $this->created_at,
        ];
    }
}
