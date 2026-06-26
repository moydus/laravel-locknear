<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderGrowthScore extends Model
{
    protected $fillable = [
        'company_id', 'score', 'profile_completion_percent', 'claim_completed',
        'verified', 'online_enabled', 'has_photo', 'insurance_uploaded',
        'first_job_completed', 'five_reviews_reached', 'breakdown',
        'calculated_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'profile_completion_percent' => 'integer',
        'claim_completed' => 'boolean',
        'verified' => 'boolean',
        'online_enabled' => 'boolean',
        'has_photo' => 'boolean',
        'insurance_uploaded' => 'boolean',
        'first_job_completed' => 'boolean',
        'five_reviews_reached' => 'boolean',
        'breakdown' => 'array',
        'calculated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
