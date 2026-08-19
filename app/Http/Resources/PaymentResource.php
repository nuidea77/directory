<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'listing_id' => $this->listing_id,
            'provider' => $this->provider,
            'plan' => $this->plan,
            'amount' => $this->amount,
            'status' => $this->status,
            'invoice_url' => $this->invoice_url,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
        ];
    }
}
