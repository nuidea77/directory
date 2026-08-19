<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;

class PhoneVerification extends Model
{
    use HasFactory, Prunable;

    /**
     * 7 хоногоос хуучин бичлэгүүдийг model:prune устгана (код, callback token агуулдаг).
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(7));
    }

    protected $fillable = [
        'uuid',
        'phone',
        'purpose',
        'code',
        'session_id',
        'sms_uri',
        'display_instruction',
        'status',
        'callback_token',
        'meta',
        'last_checked_at',
        'verified_at',
        'expires_at',
    ];

    protected $hidden = ['callback_token', 'meta', 'code'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'last_checked_at' => 'datetime',
            'verified_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }
}
