<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderDocument extends Model
{
    protected $fillable = [
        'company_id',
        'type',
        'status',
        'file_url',
        'document_number',
        'issuing_state',
        'verification_provider',
        'verification_notes',
        'verified_at',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
