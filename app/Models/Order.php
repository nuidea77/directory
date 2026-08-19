<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
        'user_id',
        'organization_id',
        'total',
        'status',
        'byl_checkout_id',
        'invoice_url',
        'provider_payload',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'provider_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Давхардахгүй захиалгын дугаар. Санамсаргүй 4 оронтой тоо сард ердөө
     * 9,999 хувилбартай тул түүнийг шавхахад мөнхийн давталтад ордог байсан —
     * одоо сарын дарааллын дугаараас (id) үүсгэнэ.
     */
    public static function generateNumber(): string
    {
        $prefix = 'KH-'.now()->format('Y-m').'-';
        $seq = static::where('number', 'like', $prefix.'%')->count() + 1;

        // Зэрэг үүсгэх үед мөргөлдвөл дараагийн сул дугаар руу шилжинэ
        for ($i = 0; $i < 50; $i++) {
            $number = $prefix.str_pad((string) ($seq + $i), 4, '0', STR_PAD_LEFT);

            if (! static::where('number', $number)->exists()) {
                return $number;
            }
        }

        return $prefix.now()->format('dHis').random_int(10, 99);
    }
}
