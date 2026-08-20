<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    use HasFactory;

    public const SCOPES = ['subscription', 'ad'];

    protected $fillable = [
        'code',
        'scope',
        'type',
        'value',
        'min_amount',
        'max_uses',
        'max_uses_per_user',
        'starts_at',
        'expires_at',
        'is_active',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    /**
     * Кодыг үргэлж ТОМ үсэг, зайгүйгээр хадгална/хайна.
     */
    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = static::normalize($value);
    }

    public function isWithinWindow(): bool
    {
        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    /**
     * Нийт ашиглалтын лимит дүүрсэн эсэх. Төлбөр хүлээж буй захиалгууд ч
     * кодыг барина — эс бөгөөс лимитээс хэтэрч ашиглагдана.
     */
    public function usesLeft(): ?int
    {
        if ($this->max_uses === null) {
            return null; // хязгааргүй
        }

        $held = Order::where('promo_code_id', $this->id)->where('status', 'pending')->count();

        return max(0, $this->max_uses - $this->used_count - $held);
    }

    /**
     * Тухайн хэрэглэгч энэ кодыг хэдэн удаа ашигласан (хүлээгдэж буйг оруулна).
     */
    public function usesByUser(int $userId): int
    {
        return $this->redemptions()->where('user_id', $userId)->count()
            + Order::where('promo_code_id', $this->id)
                ->where('user_id', $userId)
                ->where('status', 'pending')
                ->count();
    }

    /**
     * Хөнгөлөлтийн дүн — өгөгдсөн дүнгээс хэтрэхгүй.
     */
    public function discountFor(int $amount): int
    {
        $discount = $this->type === 'percent'
            ? (int) round($amount * min(100, $this->value) / 100)
            : (int) $this->value;

        return max(0, min($discount, $amount));
    }

    public function scopeLabel(): string
    {
        return $this->scope === 'ad' ? 'Сурталчилгаа' : 'Эрхийн бичиг';
    }
}
