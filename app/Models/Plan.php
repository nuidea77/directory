<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Plan extends Model
{
    protected $fillable = [
        'key',
        'name',
        'price',
        'term_years',
        'limits',
        'analytics',
        'top_list',
        'verified_badge',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'analytics' => 'boolean',
            'top_list' => 'boolean',
            'verified_badge' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * config('billing.plans')-ийн хэлбэрт хөрвүүлнэ — bootstrap үед
     * config-ийг override хийхэд ашиглана.
     */
    public static function asConfig(): array
    {
        return static::orderBy('sort_order')->get()->mapWithKeys(fn (Plan $plan) => [
            $plan->key => [
                'name' => $plan->name,
                'price' => (int) $plan->price,
                'term_years' => (int) $plan->term_years,
                'limits' => $plan->limits,
                'analytics' => $plan->analytics,
                'top_list' => $plan->top_list,
                'verified_badge' => $plan->verified_badge,
                'is_active' => $plan->is_active,
            ],
        ])->all();
    }

    public static function flushCache(): void
    {
        Cache::forget('plans:config');
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }
}
