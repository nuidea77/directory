<?php

namespace App\Models;

use App\Support\SearchText;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ярианы нэр → ангилал. «тог» гэж хайхад Цахилгаанчин олдоно.
 */
class SearchAlias extends Model
{
    protected $fillable = ['category_id', 'term', 'term_key'];

    protected static function booted(): void
    {
        // term_key нь үргэлж term-ээс бодогдоно
        static::saving(function (SearchAlias $alias) {
            $alias->term_key = SearchText::fold($alias->term);
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
