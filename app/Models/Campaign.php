<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'business_id',
        'branch_id',
        'order_id',
        'type',
        'category_id',
        'district',
        'city',
        'keyword',
        'slot',
        'days',
        'price',
        'status',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('ends_at', '>', now());
    }

    /**
     * Тухайн зайн орон зайд идэвхтэй кампанит ажлын тоо.
     */
    public static function occupiedSlots(string $type, ?int $categoryId = null, ?string $district = null, ?string $city = null, ?string $keyword = null): int
    {
        return static::query()
            ->running()
            ->where('type', $type)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->when($district, fn ($q) => $q->where('district', $district))
            ->when($city, fn ($q) => $q->where('city', $city))
            ->when($keyword, fn ($q) => $q->where('keyword', $keyword))
            ->count();
    }
}
