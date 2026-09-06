<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Ангиллын үйлчилгээ/онцлог. category_id = null бол бүх ангилалд харагдана.
 */
class Amenity extends Model
{
    protected $fillable = ['category_id', 'name', 'icon', 'sort_order'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public static function flushCache(): void
    {
        Cache::forget('amenities:map:v1');
    }
}
