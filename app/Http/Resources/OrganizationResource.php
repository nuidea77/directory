<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $effective = $this->effectivePlan();
        $config = config("billing.plans.{$effective}");

        return [
            'id' => $this->id,
            'name' => $this->name,
            'plan' => $this->plan,
            'effective_plan' => $effective,
            'plan_name' => $config['name'] ?? 'Үнэгүй',
            'plan_term_years' => $this->plan_term_years,
            'plan_period' => $this->plan_period,
            // Дуусах хугацааны UI-д: үлдсэн хоног (null = үнэгүй/хугацаагүй)
            'plan_days_left' => $this->plan_expires_at ? max(0, (int) ceil(now()->diffInDays($this->plan_expires_at, false))) : null,
            'plan_started_at' => $this->plan_started_at,
            'plan_expires_at' => $this->plan_expires_at,
            'auto_renew' => $this->auto_renew,
            'limits' => $config['limits'] ?? [],
            'analytics' => (bool) ($config['analytics'] ?? false),
            'top_list' => (bool) ($config['top_list'] ?? false),
            'businesses' => BusinessResource::collection($this->whenLoaded('businesses')),
            'created_at' => $this->created_at,
        ];
    }
}
