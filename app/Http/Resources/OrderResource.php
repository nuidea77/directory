<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'organization_id' => $this->organization_id,
            'total' => $this->total,
            'status' => $this->status,
            'invoice_url' => $this->invoice_url,
            'paid_at' => $this->paid_at,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'type' => $item->type,
                'name' => $item->name,
                'meta' => $item->meta,
                'amount' => $item->amount,
                'discount' => $item->discount,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
