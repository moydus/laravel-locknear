<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentIntent extends Model
{
    protected $fillable = [
        'booking_id',
        'lead_id',
        'company_id',
        'idempotency_key',
        'payer_type',
        'purpose',
        'status',
        'amount',
        'amount_cents',
        'captured_amount',
        'captured_amount_cents',
        'currency',
        'processor',
        'processor_intent_id',
        'processor_charge_id',
        'authorized_at',
        'captured_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'captured_amount' => 'decimal:2',
        'amount_cents' => 'integer',
        'captured_amount_cents' => 'integer',
        'authorized_at' => 'datetime',
        'captured_at' => 'datetime',
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

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}
