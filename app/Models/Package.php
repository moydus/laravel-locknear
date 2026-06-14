<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Package extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price_monthly', 'price_yearly',
        'stripe_price_id_monthly', 'stripe_price_id_yearly',
        'max_leads_per_month', 'features', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
