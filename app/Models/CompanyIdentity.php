<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyIdentity extends Model
{
    protected $fillable = [
        'company_id', 'source', 'external_id', 'google_place_id', 'apple_place_id',
        'yelp_business_id', 'website', 'phone_normalized', 'match_confidence',
        'status', 'matched_at', 'match_signals', 'metadata',
    ];

    protected $casts = [
        'match_confidence' => 'decimal:2',
        'matched_at' => 'datetime',
        'match_signals' => 'array',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
