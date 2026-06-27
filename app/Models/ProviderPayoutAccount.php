<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderPayoutAccount extends Model
{
    protected $fillable = [
        'company_id',
        'processor',
        'stripe_account_id',
        'status',
        'charges_enabled',
        'payouts_enabled',
        'onboarded_at',
        'requirements',
        'metadata',
    ];

    protected $casts = [
        'charges_enabled' => 'boolean',
        'payouts_enabled' => 'boolean',
        'onboarded_at' => 'datetime',
        'requirements' => 'array',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
