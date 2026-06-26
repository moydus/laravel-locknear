<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchDailyMetric extends Model
{
    protected $fillable = [
        'period_date', 'service_type_id', 'market', 'city', 'state', 'zip',
        'booking_count', 'dispatch_started_count', 'offer_count',
        'accepted_count', 'expired_count', 'redispatch_count',
        'completed_count', 'cancelled_count', 'acceptance_rate',
        'completion_rate', 'average_eta_minutes', 'average_response_seconds',
        'metadata',
    ];

    protected $casts = [
        'period_date' => 'date',
        'acceptance_rate' => 'decimal:2',
        'completion_rate' => 'decimal:2',
        'average_eta_minutes' => 'decimal:2',
        'metadata' => 'array',
    ];
}
