<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachMessage extends Model
{
    protected $fillable = [
        'outreach_campaign_id', 'provider_invitation_id', 'company_id',
        'channel', 'recipient', 'status', 'provider_message_id', 'queued_at',
        'sent_at', 'delivered_at', 'opened_at', 'clicked_at', 'claimed_at',
        'bounced_at', 'payload', 'metadata',
    ];

    protected $casts = [
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
        'claimed_at' => 'datetime',
        'bounced_at' => 'datetime',
        'payload' => 'array',
        'metadata' => 'array',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(OutreachCampaign::class, 'outreach_campaign_id');
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(ProviderInvitation::class, 'provider_invitation_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
