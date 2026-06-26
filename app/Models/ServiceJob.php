<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceJob extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'booking_id', 'lead_id', 'company_id', 'public_id',
        'idempotency_key', 'status', 'accepted_at', 'en_route_at',
        'arrived_at', 'started_at', 'completed_at', 'cancelled_at',
        'cancellation_reason', 'no_show_party', 'metadata',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'en_route_at' => 'datetime',
        'arrived_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DomainEvent::class, 'job_id');
    }
}
