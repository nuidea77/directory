<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    use HasFactory;

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
