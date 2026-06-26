<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProviderInvitation extends Model
{
    protected $fillable = [
        'company_id', 'outreach_campaign_id', 'phone', 'email', 'token',
        'status', 'sent_at', 'expires_at', 'accepted_at', 'metadata',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'outreach_campaign_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OutreachMessage::class);
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(ProviderCrmActivity::class);
    }
}
