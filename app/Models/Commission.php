<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    protected $fillable = [
        'booking_id',
        'job_id',
        'company_id',
        'payment_transaction_id',
        'rate',
        'service_total',
        'service_total_cents',
        'platform_fee',
        'platform_fee_cents',
        'provider_amount',
        'provider_amount_cents',
        'tax_amount',
        'tax_amount_cents',
        'tip_amount',
        'tip_amount_cents',
        'discount_amount',
        'discount_amount_cents',
        'currency',
        'status',
        'collected_at',
        'metadata',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'service_total' => 'decimal:2',
        'platform_fee' => 'decimal:2',
        'provider_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'service_total_cents' => 'integer',
        'platform_fee_cents' => 'integer',
        'provider_amount_cents' => 'integer',
        'tax_amount_cents' => 'integer',
        'tip_amount_cents' => 'integer',
        'discount_amount_cents' => 'integer',
        'collected_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function paymentTransaction(): BelongsTo
    {
        return $this->belongsTo(PaymentTransaction::class);
    }
}
