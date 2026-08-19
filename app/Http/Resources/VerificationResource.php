<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VerificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'phone' => $this->phone,
            'purpose' => $this->purpose,
            'status' => $this->status,
            'sms_uri' => $this->sms_uri,
            'display_instruction' => $this->display_instruction,
            'expires_at' => $this->expires_at,
            // Клиентийн цагны зөрүүнээс хамаарахгүй countdown-д зориулж
            // үлдсэн хугацааг секундээр өгнө
            'expires_in' => $this->expires_at ? max(0, (int) now()->diffInSeconds($this->expires_at, false)) : null,
        ];
    }
}
