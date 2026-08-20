<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'name',
        'plan',
        'plan_term_years',
        'plan_period',
        'plan_started_at',
        'plan_expires_at',
        'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'plan_started_at' => 'datetime',
            'plan_expires_at' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * Effective plan: expired paid plans fall back to free limits
     * (мэдээлэл устахгүй, зөвхөн үнэгүй эрхийн хязгаарт шилжинэ).
     */
    public function effectivePlan(): string
    {
        if ($this->plan === 'free') {
            return 'free';
        }

        return ($this->plan_expires_at === null || $this->plan_expires_at->isPast()) ? 'free' : $this->plan;
    }

    public function planConfig(): array
    {
        return config('billing.plans.'.$this->effectivePlan());
    }

    public function planLimit(string $key): int
    {
        return (int) ($this->planConfig()['limits'][$key] ?? 0);
    }

    /**
     * Байгууллагын нийт салбарын лимит (0 = хязгааргүй).
     * Нэмэлт салбарын эрх нь БАЙГУУЛЛАГЫН хэмжээнд худалдагддаг тул
     * бизнес тус бүрд давхардуулж тоолохгүй.
     */
    public function branchLimit(): int
    {
        $perBusiness = $this->planLimit('branches');

        if ($perBusiness === 0) {
            return 0;
        }

        return $perBusiness * max(1, $this->planLimit('businesses')) + (int) $this->extra_branches;
    }

    /**
     * Байгууллагын бүх бизнесийн салбарын нийт тоо.
     */
    public function branchCount(): int
    {
        return Branch::whereIn('business_id', $this->businesses()->select('id'))->count();
    }
}
