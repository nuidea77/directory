<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Зээл, хэсэгчилсэн төлбөрийн апп (LendMN, Storepay ...).
 */
class PaymentApp extends Model
{
    protected $fillable = ['slug', 'name', 'color', 'logo_path', 'wordmark', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'wordmark' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function flushCache(): void
    {
        Cache::forget('payments:list:v1');
    }
}
