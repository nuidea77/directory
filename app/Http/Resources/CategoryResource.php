<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'parent_id' => $this->parent_id,
            'depth' => $this->depth(),
            'businesses_count' => $this->whenCounted('businesses'),
            // Дэд ангиллууд (рекурсив — ачаалсан гүн хүртэл)
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
