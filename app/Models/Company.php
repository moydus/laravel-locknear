<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'slug', 'phone', 'email', 'website', 'description',
        'address', 'city', 'state', 'zip', 'latitude', 'longitude',
        'license_number', 'is_insured', 'is_verified', 'is_active',
        'logo_url', 'stripe_customer_id', 'service_areas', 'rating', 'review_count',
    ];

    protected $casts = [
        'is_insured' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'service_areas' => 'array',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'rating' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(CompanyService::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(LeadAssignment::class);
    }

    public function subscription(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function activeSubscription(): ?\App\Models\Subscription
    {
        return $this->subscription()->where('status', 'active')->latest()->first();
    }
}
