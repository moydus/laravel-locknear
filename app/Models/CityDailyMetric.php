<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CityDailyMetric extends Model
{
    protected $fillable = [
        'period_date', 'market', 'city', 'state', 'service_type_id',
        'booking_count', 'completed_count', 'directory_provider_count',
        'claimed_provider_count', 'verified_provider_count',
        'online_provider_count', 'coverage_percent', 'average_price',
        'average_eta_minutes', 'metadata',
    ];

    protected $casts = [
        'period_date' => 'date',
        'coverage_percent' => 'decimal:2',
        'average_price' => 'decimal:2',
        'average_eta_minutes' => 'decimal:2',
        'metadata' => 'array',
    ];
}
