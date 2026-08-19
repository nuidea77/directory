<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'password',
        'avatar_path',
        // phone_verified_at санаатайгаар байхгүй — зөвхөн forceFill/query builder-ээр
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
        ];
    }

    public function organizations(): HasMany
    {
        return $this->hasMany(Organization::class, 'owner_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function hasVerifiedPhone(): bool
    {
        return $this->phone_verified_at !== null;
    }

    /**
     * Тоймчийн зэрэглэл (Lv) — бичсэн сэтгэгдлийн тооноос.
     */
    public function reviewerLevel(): array
    {
        $count = $this->reviews()->count();

        return match (true) {
            $count >= 40 => ['level' => 5, 'name' => 'Хотын домог'],
            $count >= 20 => ['level' => 4, 'name' => 'Хотын мэргэжилтэн'],
            $count >= 10 => ['level' => 3, 'name' => 'Тэргүүн тоймч'],
            $count >= 3 => ['level' => 2, 'name' => 'Идэвхтэй тоймч'],
            default => ['level' => 1, 'name' => 'Шинэ гишүүн'],
        };
    }
}
