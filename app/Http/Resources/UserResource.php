<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $level = $this->reviewerLevel();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'avatar_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'phone_verified' => $this->phone_verified_at !== null,
            'is_admin' => (bool) $this->is_admin,
            'level' => $level['level'],
            'level_name' => $level['name'],
            'created_at' => $this->created_at,
        ];
    }
}
