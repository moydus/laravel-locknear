<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lead_id', 'company_id', 'customer_address_id', 'public_id',
        'idempotency_key', 'status', 'estimated_min_amount',
        'estimated_max_amount', 'final_amount', 'currency',
        'customer_timezone', 'authorized_at', 'paid_at', 'completed_at',
        'cancelled_at', 'metadata',
    ];

    protected $casts = [
        'estimated_min_amount' => 'decimal:2',
        'estimated_max_amount' => 'decimal:2',
        'final_amount' => 'decimal:2',
        'authorized_at' => 'datetime',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function serviceJob(): HasOne
    {
        return $this->hasOne(ServiceJob::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DomainEvent::class);
    }
}
