<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OutreachCampaign extends Model
{
    protected $fillable = [
        'name', 'market', 'city', 'state', 'zip', 'status', 'channel_mix',
        'target_count', 'sent_count', 'claimed_count', 'started_at',
        'completed_at', 'metadata',
    ];

    protected $casts = [
        'target_count' => 'integer',
        'sent_count' => 'integer',
        'claimed_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(OutreachMessage::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProviderInvitation::class);
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(ProviderCrmActivity::class);
    }
}
