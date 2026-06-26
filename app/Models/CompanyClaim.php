<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyClaim extends Model
{
    protected $fillable = [
        'company_id', 'user_id', 'status', 'verification_method',
        'verification_channel', 'verification_target', 'claimed_at',
        'approved_at', 'rejected_at', 'evidence', 'metadata',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'evidence' => 'array',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
