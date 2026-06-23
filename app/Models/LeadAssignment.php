<?php

namespace App\Models;

use App\Support\DispatchEta;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignment extends Model
{
    protected $fillable = [
        'lead_id', 'company_id', 'status', 'lead_cost', 'stripe_charge_id', 'responded_at',
        'accepted_at', 'en_route_at', 'arrived_at', 'completed_at',
        'provider_latitude', 'provider_longitude', 'last_location_at',
    ];

    protected $appends = [
        'eta_minutes',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'accepted_at' => 'datetime',
        'en_route_at' => 'datetime',
        'arrived_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_location_at' => 'datetime',
        'lead_cost' => 'decimal:2',
        'provider_latitude' => 'decimal:7',
        'provider_longitude' => 'decimal:7',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function getEtaMinutesAttribute(): ?int
    {
        return DispatchEta::estimateMinutes($this->lead, $this);
    }
}
