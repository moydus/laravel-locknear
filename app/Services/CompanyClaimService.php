<?php

namespace App\Services;

use App\Enums\CompanyLifecycleStatus;
use App\Enums\ProviderStatus;
use App\Models\Company;
use App\Models\CompanyClaim;
use App\Models\CompanyService;
use App\Models\CompanyIdentity;
use App\Models\ProviderAccount;
use App\Models\ProviderAccountUser;
use App\Models\ProviderGrowthScore;
use App\Models\ProviderInvitation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class CompanyClaimService
{
    private const DEFAULT_CLAIM_BUSINESS_TYPE = 'mobile-24-7';

    private const BUSINESS_SERVICES = [
        'residential' => ['house-lockout', 'lock-rekey', 'emergency'],
        'commercial' => ['commercial', 'lock-rekey'],
        'automotive' => ['car-lockout', 'car-key-replacement', 'key-fob-programming'],
        'mobile-24-7' => ['house-lockout', 'car-lockout', 'commercial', 'emergency', '24-hour-locksmith'],
    ];

    public function findClaimableCompany(string $token): ?Company
    {
        return Company::where('claim_token', $token)
            ->whereNull('deleted_at')
            ->where('is_claimed', false)
            ->first();
    }

    public function claimForUser(Company $company, User $user): Company
    {
        if ($company->is_claimed) {
            throw ValidationException::withMessages([
                'claim_token' => ['This listing has already been claimed.'],
            ]);
        }

        if ($user->company && (int) $user->company->id !== (int) $company->id) {
            throw ValidationException::withMessages([
                'claim_token' => ['Your account is already linked to another company.'],
            ]);
        }

        $businessType = $company->business_type ?: self::DEFAULT_CLAIM_BUSINESS_TYPE;
        $claimToken = $company->claim_token;

        $company->update([
            'user_id' => $user->id,
            'is_claimed' => true,
            'is_active' => true,
            'provider_status' => ProviderStatus::Verified,
            'lifecycle_status' => CompanyLifecycleStatus::Active,
            'claimed_at' => now(),
            'claim_token' => null,
            'source' => 'claimed',
            'business_type' => $businessType,
        ]);

        $this->syncDefaultServices($company->fresh(), $businessType);

        CompanyClaim::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'verification_method' => 'claim_token',
            'verification_channel' => 'link',
            'verification_target' => $company->phone ?: $company->email,
            'claimed_at' => now(),
            'approved_at' => now(),
            'metadata' => [
                'source' => 'claim_link',
                'claim_token' => $claimToken,
            ],
        ]);

        $this->bootstrapProviderAccount($company, $user->id, true);
        $this->upsertCompanyIdentity($company, 'claimed');

        ProviderInvitation::query()
            ->where('company_id', $company->id)
            ->whereNull('accepted_at')
            ->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'updated_at' => now(),
            ]);

        return $company->fresh();
    }

    public function claimResponse(Company $company): JsonResponse
    {
        return response()->json([
            'success' => true,
            'company' => ['id' => $company->id, 'name' => $company->name],
        ]);
    }

    private function bootstrapProviderAccount(Company $company, int $userId, bool $verified): void
    {
        ProviderAccount::firstOrCreate(
            ['company_id' => $company->id],
            [
                'status' => $verified ? 'active' : 'pending',
                'display_name' => $company->name,
                'timezone' => $company->timezone ?: 'America/New_York',
                'default_capacity' => 1,
                'capabilities' => ['dispatch', 'crm'],
            ],
        );

        ProviderAccountUser::firstOrCreate(
            ['company_id' => $company->id, 'user_id' => $userId],
            [
                'role' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
                'permissions' => ['*'],
            ],
        );

        ProviderGrowthScore::updateOrCreate(
            ['company_id' => $company->id],
            [
                'claim_completed' => true,
                'verified' => $verified,
                'online_enabled' => (bool) $company->is_online,
                'has_photo' => filled($company->logo_url),
                'insurance_uploaded' => (bool) $company->is_insured,
                'score' => $verified ? 40 : 20,
                'profile_completion_percent' => $verified ? 40 : 25,
                'breakdown' => [
                    'claim_completed' => 20,
                    'verified' => $verified ? 20 : 0,
                ],
                'calculated_at' => now(),
            ],
        );
    }

    private function upsertCompanyIdentity(Company $company, string $source): void
    {
        CompanyIdentity::updateOrCreate(
            [
                'company_id' => $company->id,
                'source' => $source,
                'phone_normalized' => $this->normalizePhone($company->phone),
            ],
            [
                'external_id' => $company->google_place_id,
                'google_place_id' => $company->google_place_id,
                'website' => $company->website,
                'match_confidence' => 100,
                'status' => 'matched',
                'matched_at' => now(),
                'match_signals' => [
                    'phone' => filled($company->phone),
                    'website' => filled($company->website),
                    'google_place_id' => filled($company->google_place_id),
                ],
            ],
        );
    }

    private function syncDefaultServices(Company $company, string $businessType): void
    {
        $types = self::BUSINESS_SERVICES[$businessType] ?? [];

        foreach ($types as $serviceType) {
            CompanyService::updateOrCreate(
                ['company_id' => $company->id, 'service_type' => $serviceType],
                ['is_active' => true],
            );
        }
    }

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }
}
