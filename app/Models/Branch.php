<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    public const WEEKDAYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    protected $fillable = [
        'business_id',
        'name',
        'slug',
        'is_main',
        'city',
        'district',
        'khoroo',
        'address',
        'landmark',
        'lat',
        'lng',
        'phone',
        'phone_verified',
        'email',
        'hours',
        'amenities',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_main' => 'boolean',
            'phone_verified' => 'boolean',
            'hours' => 'array',
            'amenities' => 'array',
            'lat' => 'float',
            'lng' => 'float',
            'rating_avg' => 'float',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(BranchImage::class)->orderByDesc('is_cover')->orderBy('sort_order');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)->latest();
    }

    public function stats(): HasMany
    {
        return $this->hasMany(BranchStat::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function refreshRating(): void
    {
        $stats = $this->reviews()->where('status', 'active')
            ->selectRaw('COUNT(*) as cnt, COALESCE(AVG(rating), 0) as avg')->first();

        $this->forceFill([
            'reviews_count' => (int) $stats->cnt,
            'rating_avg' => round((float) $stats->avg, 2),
        ])->save();
    }

    /**
     * Одоо нээлттэй эсэх, өнөөдрийн хаах цаг.
     * hours: {mon: {from: "09:00", to: "19:00", closed: false}, ...}
     */
    public function openState(): array
    {
        $hours = $this->hours ?? [];
        $key = self::WEEKDAYS[now()->dayOfWeekIso - 1];
        $today = $hours[$key] ?? null;

        if ($today === null || ! empty($today['closed']) || empty($today['from']) || empty($today['to'])) {
            return ['open' => false, 'label' => 'Хаагдсан'];
        }

        $time = now()->format('H:i');

        if ($time >= $today['from'] && $time < $today['to']) {
            return ['open' => true, 'label' => 'Нээлттэй · '.$today['to'].' хүртэл'];
        }

        return ['open' => false, 'label' => $time < $today['from'] ? $today['from'].'-д нээнэ' : 'Хаагдсан'];
    }

    /**
     * Бүрэн байдлын хувь (профайлын checklist).
     */
    public function completeness(): int
    {
        $checks = [
            filled($this->address),
            $this->lat !== null && $this->lng !== null,
            ! empty($this->hours),
            $this->images()->count() >= 3,
            ! empty($this->amenities),
            $this->phone_verified,
        ];

        return (int) round(collect($checks)->filter()->count() / count($checks) * 100);
    }

    public function recordEvent(string $type, ?string $source = null): void
    {
        $column = match ($type) {
            'view' => 'views_count',
            'call' => 'calls_count',
            'direction' => 'directions_count',
            default => null,
        };

        if ($column === null) {
            return;
        }

        $this->increment($column);

        // Carbon ашиглана — where/insert хоёулаа ижил datetime форматтай байхын тулд
        $stat = BranchStat::firstOrCreate(
            ['branch_id' => $this->id, 'date' => now()->startOfDay()],
        );

        $stat->increment(match ($type) {
            'view' => 'views',
            'call' => 'calls',
            'direction' => 'directions',
        });

        if ($type === 'view' && in_array($source, ['category', 'search', 'map', 'direct'], true)) {
            $stat->increment('views_'.$source);
        }
    }
}
