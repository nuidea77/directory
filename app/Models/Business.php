<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'category_id',
        'subcategory',
        'name',
        'slug',
        'description',
        'logo_path',
        'website',
        'facebook',
        'instagram',
        'price_level',
        'is_verified',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class)->orderByDesc('is_main');
    }

    public function activeBranches(): HasMany
    {
        return $this->branches()->where('status', 'active');
    }

    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(Review::class, Branch::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    public function scopeWithPublicStats(Builder $query): Builder
    {
        // Нийт үнэлгээ = идэвхтэй салбаруудын жинлэсэн дундаж
        return $query->withCount(['branches as active_branches_count' => fn ($q) => $q->where('status', 'active')]);
    }

    public function ratingAvg(): float
    {
        $branches = $this->relationLoaded('branches') ? $this->branches : $this->branches()->get();
        $active = $branches->where('status', 'active');
        $total = $active->sum('reviews_count');

        if ($total === 0) {
            return 0.0;
        }

        $weighted = $active->sum(fn (Branch $b) => $b->rating_avg * $b->reviews_count);

        return round($weighted / $total, 1);
    }

    public function reviewsTotal(): int
    {
        $branches = $this->relationLoaded('branches') ? $this->branches : $this->branches()->get();

        return (int) $branches->where('status', 'active')->sum('reviews_count');
    }

    /**
     * Идэвхтэй онцлох кампанит ажилтай эсэх.
     */
    public function hasActiveCampaign(?string $type = null): bool
    {
        return $this->campaigns()
            ->where('status', 'active')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->where('ends_at', '>', now())
            ->exists();
    }
}
