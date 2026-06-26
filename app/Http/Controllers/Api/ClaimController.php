<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompanyLifecycleStatus;
use App\Enums\ProviderStatus;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyClaim;
use App\Models\CompanyIdentity;
use App\Models\ProviderAccount;
use App\Models\ProviderAccountUser;
use App\Models\ProviderGrowthScore;
use App\Models\ProviderInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClaimController extends Controller
{
    // GET /claim/{token}  — token bilgisini döner (panel sayfası yüklenirken)
    public function show(string $token): JsonResponse
    {
        $company = Company::where('claim_token', $token)
            ->whereNull('deleted_at')
            ->first();

        if (!$company) {
            return response()->json(['error' => 'Invalid or expired claim link'], 404);
        }

        if ($company->is_claimed) {
            return response()->json(['error' => 'This listing has already been claimed'], 409);
        }

        return response()->json([
            'company' => [
                'id'    => $company->id,
                'name'  => $company->name,
                'city'  => $company->city,
                'state' => $company->state,
                'phone' => $company->phone,
            ],
        ]);
    }

    // POST /claim/{token}  — mevcut oturumdaki kullanıcıya firmayı bağlar
    public function claim(Request $request, string $token): JsonResponse
    {
        $company = Company::where('claim_token', $token)
            ->whereNull('deleted_at')
            ->first();

        if (!$company) {
            return response()->json(['error' => 'Invalid or expired claim link'], 404);
        }

        if ($company->is_claimed) {
            return response()->json(['error' => 'Already claimed'], 409);
        }

        $user = $request->user();

        // Kullanıcının zaten başka bir firması varsa reddet
        if ($user->company && $user->company->id !== $company->id) {
            return response()->json(['error' => 'Your account is already linked to another company'], 422);
        }

        $company->update([
            'user_id'     => $user->id,
            'is_claimed'  => true,
            'is_active'   => true,
            'provider_status' => ProviderStatus::Verified,
            'lifecycle_status' => CompanyLifecycleStatus::Active,
            'claimed_at'  => now(),
            'claim_token' => null,       // tek kullanım
            'source'      => 'claimed',
        ]);

        CompanyClaim::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'approved',
            'verification_method' => 'claim_token',
            'verification_channel' => 'link',
            'verification_target' => $company->phone ?: $company->email,
            'claimed_at' => now(),
            'approved_at' => now(),
            'metadata' => ['source' => 'claim_link'],
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

        return response()->json([
            'success' => true,
            'company' => ['id' => $company->id, 'name' => $company->name],
        ]);
    }

    // POST /claim/manual  — claim token olmadan yeni firma oluşturur (doğrudan kayıt)
    public function manual(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->company) {
            return response()->json(['error' => 'Already has a company'], 422);
        }

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'zip'   => ['required', 'string', 'size:5'],
            'city'  => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2'],
        ]);

        $company = Company::create([
            'user_id'    => $user->id,
            'name'       => $validated['name'],
            'slug'       => str($validated['name'])->slug()->append('-' . Str::random(6)),
            'phone'      => $validated['phone'],
            'zip'        => $validated['zip'],
            'city'       => $validated['city'],
            'state'      => strtoupper($validated['state']),
            'is_claimed' => true,
            'is_active'  => false,  // admin onayı veya profil tamamlanması gerekiyor
            'provider_status' => ProviderStatus::Pending,
            'lifecycle_status' => CompanyLifecycleStatus::ClaimPending,
            'claimed_at' => now(),
            'source'     => 'manual',
        ]);

        CompanyClaim::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'status' => 'pending',
            'verification_method' => 'manual',
            'verification_channel' => 'app',
            'verification_target' => $validated['phone'],
            'claimed_at' => now(),
            'metadata' => ['source' => 'manual_signup'],
        ]);

        $this->bootstrapProviderAccount($company, $user->id, false);
        $this->upsertCompanyIdentity($company, 'manual');

        return response()->json([
            'success' => true,
            'company' => ['id' => $company->id, 'name' => $company->name],
        ], 201);
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

    private function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }
}
