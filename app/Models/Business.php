<?php

namespace App\Models;

use App\Services\SearchIndexer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

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
        'email',
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

    /**
     * Нэг бизнес олон ангилалд бүртгэгдэж болно (ж: үсчин + хумсны засал).
     * Хамгийн ихдээ хэдэн ангилал сонгож болох — үндсэнийг оруулаад.
     */
    public const MAX_CATEGORIES = 5;

    protected static function booted(): void
    {
        // Үндсэн ангилал ҮРГЭЛЖ pivot дотор байна — хайлт зөвхөн pivot-оор
        // ажилладаг тул энэ нь мод дүүрэн байхыг баталгаажуулна
        static::saved(function (Business $business) {
            if ($business->category_id) {
                $business->categories()->syncWithoutDetaching([$business->category_id]);
            }
        });

        // Нэр, ангилал, тайлбар солигдоход салбаруудын индекс шинэчлэгдэнэ
        static::saved(function (Business $business) {
            app(SearchIndexer::class)->indexBusiness($business);
        });

        static::deleting(function (Business $business) {
            // DB cascade Eloquent event дуудахгүй тул салбаруудыг моделиор устгана
            $business->branches()->get()->each->delete();

            if ($business->logo_path) {
                Storage::disk('public')->delete($business->logo_path);
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Үндсэн + нэмэлт ангиллууд (хайлт, ангиллын жагсаалт эндээс ажиллана).
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->orderBy('sort_order');
    }

    /**
     * Ангиллын багцыг бүхэлд нь солино — үндсэн ангилал үргэлж эхэнд.
     */
    public function syncCategories(array $ids): void
    {
        $ids = collect($ids)->map(fn ($id) => (int) $id)
            ->prepend((int) $this->category_id)
            ->filter()
            ->unique()
            ->take(self::MAX_CATEGORIES)
            ->all();

        $this->categories()->sync($ids);
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
