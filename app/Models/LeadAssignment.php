<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadAssignment extends Model
{
    protected $fillable = [
        'lead_id', 'company_id', 'status', 'lead_cost', 'stripe_charge_id', 'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
        'lead_cost' => 'decimal:2',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
