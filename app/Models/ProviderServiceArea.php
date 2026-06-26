<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderServiceArea extends Model
{
    protected $fillable = [
        'company_id',
        'city',
        'state',
        'zip',
        'latitude',
        'longitude',
        'radius_miles',
        'is_active',
        'version',
        'effective_at',
        'retired_at',
        'metadata',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'radius_miles' => 'integer',
        'is_active' => 'boolean',
        'version' => 'integer',
        'effective_at' => 'datetime',
        'retired_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
