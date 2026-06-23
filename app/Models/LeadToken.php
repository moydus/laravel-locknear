<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadToken extends Model
{
    protected $fillable = ['lead_id', 'company_id', 'token', 'type', 'used_at', 'expires_at'];

    protected $casts = [
        'used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public static function generate(int $leadId, int $companyId, string $type, int $minutesTTL = 60): self
    {
        return self::create([
            'lead_id' => $leadId,
            'company_id' => $companyId,
            'token' => Str::random(48),
            'type' => $type,
            'expires_at' => now()->addMinutes($minutesTTL),
        ]);
    }

    public function isValid(): bool
    {
        return is_null($this->used_at) && $this->expires_at->isFuture();
    }

    public function markUsed(): void
    {
        $this->update(['used_at' => now()]);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
