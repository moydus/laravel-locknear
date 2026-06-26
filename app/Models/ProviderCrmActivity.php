<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProviderCrmActivity extends Model
{
    protected $fillable = [
        'company_id', 'outreach_campaign_id', 'provider_invitation_id',
        'user_id', 'type', 'status', 'outcome', 'contact_name',
        'contact_phone', 'contact_email', 'scheduled_at', 'completed_at',
        'next_follow_up_at', 'notes', 'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'next_follow_up_at' => 'datetime',
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

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ProviderInvitation::class, 'provider_invitation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
