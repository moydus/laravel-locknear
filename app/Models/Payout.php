<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $fillable = [
        'company_id',
        'status',
        'gross_amount',
        'gross_amount_cents',
        'fee_amount',
        'fee_amount_cents',
        'net_amount',
        'net_amount_cents',
        'currency',
        'processor',
        'stripe_account_id',
        'stripe_transfer_id',
        'stripe_payout_id',
        'period_start',
        'period_end',
        'available_on',
        'scheduled_at',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'gross_amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'gross_amount_cents' => 'integer',
        'fee_amount_cents' => 'integer',
        'net_amount_cents' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'available_on' => 'datetime',
        'scheduled_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
