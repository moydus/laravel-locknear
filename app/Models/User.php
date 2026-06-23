<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'google_id', 'is_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, FilamentUser
{
    public const ROLE_CUSTOMER = 'customer';

    public const ROLE_BUSINESS = 'business';

    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmailTrait, Notifiable, HasApiTokens;

    public function canAccessPanel(Panel $panel): bool
    {
        $allowed = array_map('trim', explode(',', env('FILAMENT_ADMIN_EMAILS', '')));
        return in_array($this->email, $allowed, true);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_CUSTOMER;
    }

    public function isBusiness(): bool
    {
        return $this->role === self::ROLE_BUSINESS;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
