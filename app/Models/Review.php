<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $fillable = ['listing_id', 'user_id', 'rating', 'comment'];

    protected static function booted(): void
    {
        static::saved(fn (Review $review) => $review->listing?->refreshRating());
        static::deleted(fn (Review $review) => $review->listing?->refreshRating());
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
