<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $fillable = [
        'booking_id',
        'payment_intent_id',
        'lead_id',
        'company_id',
        'type',
        'status',
        'gross_amount',
        'gross_amount_cents',
        'fee_amount',
        'fee_amount_cents',
        'net_amount',
        'net_amount_cents',
        'currency',
        'processor',
        'processor_id',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'gross_amount_cents' => 'integer',
        'fee_amount_cents' => 'integer',
        'net_amount_cents' => 'integer',
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
