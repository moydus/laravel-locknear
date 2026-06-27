<?php

namespace App\Models;

use App\Enums\CompanyLifecycleStatus;
use App\Enums\ProviderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

class Company extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'public_id', 'name', 'slug', 'phone', 'email', 'website', 'description',
        'address', 'city', 'state', 'zip',
        'google_place_id', 'formatted_address', 'address_components', 'place_source', 'place_verified_at',
        'latitude', 'longitude',
        'license_number', 'is_insured', 'is_verified', 'is_active', 'provider_status', 'is_online', 'is_claimed',
        'claimed_at', 'last_seen_at', 'claim_token', 'source', 'source_last_synced_at',
        'logo_url', 'stripe_customer_id', 'service_areas', 'rating', 'review_count',
        'business_type', 'timezone', 'lifecycle_status', 'onboarding_completed_at',
    ];

    protected $casts = [
        'is_insured' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'is_claimed' => 'boolean',
        'claimed_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'source_last_synced_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
        'place_verified_at' => 'datetime',
        'address_components' => 'array',
        'service_areas' => 'array',
        'provider_status' => ProviderStatus::class,
        'lifecycle_status' => CompanyLifecycleStatus::class,
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'rating' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (!$company->is_claimed && empty($company->claim_token)) {
                $company->claim_token = Str::random(48);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(CompanyService::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(LeadAssignment::class);
    }

    public function subscription(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(CompanySource::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(CompanyIdentity::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(CompanyClaim::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProviderInvitation::class);
    }

    public function providerAccount(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProviderAccount::class);
    }

    public function providerAvailability(): HasOne
    {
        return $this->hasOne(ProviderAvailability::class);
    }

    public function payoutAccount(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProviderPayoutAccount::class);
    }

    public function providerUsers(): HasMany
    {
        return $this->hasMany(ProviderAccountUser::class);
    }

    public function growthScore(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProviderGrowthScore::class);
    }

    public function crmActivities(): HasMany
    {
        return $this->hasMany(ProviderCrmActivity::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(ProviderDevice::class);
    }

    public function providerServiceAreas(): HasMany
    {
        return $this->hasMany(ProviderServiceArea::class);
    }

    public function activeSubscription(): ?\App\Models\Subscription
    {
        return $this->subscription()
            ->whereIn('status', ['active', 'trialing'])
            ->latest()
            ->first();
    }

    public function availabilityStatus(): string
    {
        $availability = $this->providerAvailability;
        $isOnline = $availability?->is_online ?? $this->is_online;
        $lastSeenAt = $availability?->last_seen_at ?? $this->last_seen_at;

        if (!$isOnline) {
            return 'offline';
        }

        if (!$lastSeenAt) {
            return 'away';
        }

        $onlineCutoff = now()->subMinutes(config('locknear.presence.online_minutes', 1));
        $awayCutoff = now()->subMinutes(config('locknear.presence.away_minutes', 2));

        if ($lastSeenAt->gte($onlineCutoff)) {
            return 'online';
        }

        if ($lastSeenAt->gte($awayCutoff)) {
            return 'away';
        }

        return 'offline';
    }

    public function isDispatchEligible(): bool
    {
        $awayCutoff = now()->subMinutes(config('locknear.presence.away_minutes', 2));
        $availability = $this->providerAvailability;
        $isOnline = $availability?->is_online ?? $this->is_online;
        $lastSeenAt = $availability?->last_seen_at ?? $this->last_seen_at;

        return $this->is_active
            && $isOnline
            && $lastSeenAt
            && $lastSeenAt->gte($awayCutoff);
    }

    public static function markStaleOffline(): int
    {
        $awayCutoff = now()->subMinutes(config('locknear.presence.away_minutes', 2));

        $count = static::query()
            ->where('is_online', true)
            ->where(function ($query) use ($awayCutoff) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $awayCutoff);
            })
            ->update(['is_online' => false]);

        ProviderAvailability::query()
            ->where('is_online', true)
            ->where(function ($query) use ($awayCutoff) {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $awayCutoff);
            })
            ->update(['is_online' => false]);

        return $count;
    }

    public function ensureClaimToken(): string
    {
        if ($this->claim_token) {
            return $this->claim_token;
        }

        $token = Str::random(48);
        $this->forceFill(['claim_token' => $token])->save();

        return $token;
    }

    public function presencePayload(): array
    {
        return [
            'is_online' => (bool) $this->is_online,
            'availability_status' => $this->availabilityStatus(),
            'last_seen_at' => $this->last_seen_at?->toISOString(),
            'dispatch_eligible' => $this->isDispatchEligible(),
        ];
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'address' => $this->address,
            'description' => $this->description,
            'service_areas' => $this->service_areas ?? [],
            'is_active' => $this->is_active,
            'is_claimed' => $this->is_claimed,
            'is_verified' => $this->is_verified,
            'rating' => (float) $this->rating,
            'review_count' => (int) $this->review_count,
        ];
    }

    public function shouldBeSearchable(): bool
    {
        return (bool) $this->is_active;
    }
}
