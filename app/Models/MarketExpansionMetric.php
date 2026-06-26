<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketExpansionMetric extends Model
{
    protected $fillable = [
        'period_date', 'market', 'city', 'state', 'zip',
        'directory_provider_count', 'claimed_provider_count',
        'verified_provider_count', 'online_provider_count',
        'booking_demand_count', 'estimated_daily_demand', 'coverage_percent',
        'recommendation', 'metadata',
    ];

    protected $casts = [
        'period_date' => 'date',
        'directory_provider_count' => 'integer',
        'claimed_provider_count' => 'integer',
        'verified_provider_count' => 'integer',
        'online_provider_count' => 'integer',
        'booking_demand_count' => 'integer',
        'estimated_daily_demand' => 'decimal:2',
        'coverage_percent' => 'decimal:2',
        'metadata' => 'array',
    ];
}
