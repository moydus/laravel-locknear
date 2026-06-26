<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderDailyMetric extends Model
{
    protected $fillable = [
        'period_date', 'company_id', 'offers_sent', 'offers_accepted',
        'offers_declined', 'offers_expired', 'jobs_completed',
        'jobs_cancelled', 'online_seconds', 'acceptance_rate',
        'completion_rate', 'quality_score', 'average_rating',
        'average_response_seconds', 'average_eta_minutes', 'metadata',
    ];

    protected $casts = [
        'period_date' => 'date',
        'acceptance_rate' => 'decimal:2',
        'completion_rate' => 'decimal:2',
        'quality_score' => 'decimal:2',
        'average_rating' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
