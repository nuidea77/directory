<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Listing extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'category_id',
        'name',
        'slug',
        'description',
        'phone',
        'email',
        'website',
        'address',
        'city',
        'district',
        'lat',
        'lng',
        'logo_path',
        'cover_path',
        'opening_hours',
        'socials',
        'status',
        'featured_until',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'socials' => 'array',
            'featured_until' => 'datetime',
            'lat' => 'float',
            'lng' => 'float',
            'rating_avg' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class)->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isFeatured(): bool
    {
        return $this->featured_until !== null && $this->featured_until->isFuture();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->whereNotNull('featured_until')->where('featured_until', '>', now());
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $term = trim($term);

        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('description', 'like', "%{$term}%")
                ->orWhere('address', 'like', "%{$term}%");
        });
    }

    /**
     * Recalculate the denormalized rating fields from the reviews table.
     */
    public function refreshRating(): void
    {
        $stats = $this->reviews()->selectRaw('COUNT(*) as cnt, COALESCE(AVG(rating), 0) as avg')->first();

        $this->forceFill([
            'reviews_count' => (int) $stats->cnt,
            'rating_avg' => round((float) $stats->avg, 2),
        ])->save();
    }
}
