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
     * Нэг «зайн орон зай» — (төрөл, ангилал, дүүрэг, хот, түлхүүр үг) яг таарах
     * ёстой. when() ашиглавал NULL нь «шүүлтгүй» болж хоёр тийш алдаа гаргадаг:
     * улс даяарын зар дүүргийнхээ зайг эзэлдэггүй, дүүргийнх нь улсынхаа
     * лимитэд буруу тоологддог байсан.
     */
    public function scopeInSlotSpace(Builder $query, string $type, ?int $categoryId = null, ?string $district = null, ?string $city = null, ?string $keyword = null): Builder
    {
        return $query->where('type', $type)
            ->where('category_id', $categoryId)
            ->where('district', $district)
            ->where('city', $city)
            ->where('keyword', $keyword);
    }

    /**
     * Зай «барьж байгаа» бүх төлөв: идэвхтэй, дараалалд, төлбөр хүлээж буй.
     * Лимит шалгахад pending_payment-ийг оруулахгүй бол нэг зайг олон хүнд
     * зэрэг зарж болдог байсан.
     */
    public function scopeHoldingSlot(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $qq) => $qq->where('status', 'active')->where('ends_at', '>', now()))
            ->orWhereIn('status', ['queued', 'pending_payment']));
    }

    /**
     * Тухайн зайн орон зайд идэвхтэй кампанит ажлын тоо.
     */
    public static function occupiedSlots(string $type, ?int $categoryId = null, ?string $district = null, ?string $city = null, ?string $keyword = null): int
    {
        return static::query()
            ->running()
            ->inSlotSpace($type, $categoryId, $district, $city, $keyword)
            ->count();
    }

    /**
     * Сул зайн хамгийн бага дугаар (1..$slots). Дунд зай сулрахад
     * «эзэлсэн + 1» гэж бодвол давхардсан дугаар өгдөг байсан.
     */
    public static function firstFreeSlot(string $type, int $slots, ?int $categoryId = null, ?string $district = null, ?string $city = null, ?string $keyword = null): ?int
    {
        $taken = static::query()
            ->running()
            ->inSlotSpace($type, $categoryId, $district, $city, $keyword)
            ->pluck('slot')
            ->filter()
            ->all();

        foreach (range(1, max(1, $slots)) as $n) {
            if (! in_array($n, $taken, true)) {
                return $n;
            }
        }

        return null;
    }
}
