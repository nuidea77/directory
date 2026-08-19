<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $fillable = ['branch_id', 'user_id', 'rating', 'comment', 'reply', 'replied_at', 'status'];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saved(fn (Review $review) => $review->branch?->refreshRating());
        static::deleted(fn (Review $review) => $review->branch?->refreshRating());
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
