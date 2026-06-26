<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySource extends Model
{
    protected $fillable = [
        'company_id', 'source', 'external_id', 'external_url', 'rating',
        'review_count', 'last_synced_at', 'metadata',
    ];

    protected $casts = [
        'rating' => 'decimal:2',
        'review_count' => 'integer',
        'last_synced_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
