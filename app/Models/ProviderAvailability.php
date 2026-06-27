<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderAvailability extends Model
{
    protected $table = 'provider_availability';

    protected $fillable = [
        'company_id',
        'is_online',
        'is_24_7',
        'max_concurrent_jobs',
        'active_jobs_count',
        'auto_accept',
        'accept_timeout_seconds',
        'weekly_hours',
        'pricing_filters',
        'last_seen_at',
        'available_until',
        'metadata',
    ];

    protected $casts = [
        'is_online' => 'boolean',
        'is_24_7' => 'boolean',
        'max_concurrent_jobs' => 'integer',
        'active_jobs_count' => 'integer',
        'auto_accept' => 'boolean',
        'accept_timeout_seconds' => 'integer',
        'weekly_hours' => 'array',
        'pricing_filters' => 'array',
        'last_seen_at' => 'datetime',
        'available_until' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
