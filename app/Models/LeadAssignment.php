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
        'verification_checklist', 'verification_checked_at', 'verification_notes',
        'service_refusal_reason', 'service_refused_at', 'dispatch_fee_eligible',
        'dispatch_fee_capture_status', 'dispatch_fee_capture_amount_cents',
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
        'verification_checklist' => 'array',
        'verification_checked_at' => 'datetime',
        'service_refused_at' => 'datetime',
        'dispatch_fee_eligible' => 'boolean',
        'dispatch_fee_capture_amount_cents' => 'integer',
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
