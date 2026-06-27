<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ProviderAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderAvailabilityController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $availability = $this->availabilityFor($company);

        return response()->json(['data' => $this->payload($company->fresh(), $availability->fresh())]);
    }

    public function update(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $validated = $request->validate([
            'is_online' => ['sometimes', 'boolean'],
            'is_24_7' => ['sometimes', 'boolean'],
            'max_concurrent_jobs' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'active_jobs_count' => ['sometimes', 'integer', 'min:0', 'max:25'],
            'auto_accept' => ['sometimes', 'boolean'],
            'accept_timeout_seconds' => ['sometimes', 'integer', 'min:5', 'max:300'],
            'weekly_hours' => ['sometimes', 'nullable', 'array'],
            'pricing_filters' => ['sometimes', 'nullable', 'array'],
            'available_until' => ['sometimes', 'nullable', 'date'],
        ]);

        $availability = $this->availabilityFor($company);

        if (($validated['is_online'] ?? null) === true && (!$company->phone || !$company->address)) {
            return response()->json([
                'message' => 'Complete your phone number and address before going online.',
            ], 422);
        }

        if (($validated['is_online'] ?? null) === true) {
            $validated['last_seen_at'] = now();
        }

        $availability->update($validated);

        if (array_key_exists('is_online', $validated)) {
            $company->forceFill([
                'is_online' => (bool) $validated['is_online'],
                'is_active' => (bool) $validated['is_online'] ? true : $company->is_active,
                'last_seen_at' => (bool) $validated['is_online'] ? now() : $company->last_seen_at,
            ])->save();
        }

        return response()->json(['data' => $this->payload($company->fresh(), $availability->fresh())]);
    }

    private function availabilityFor(Company $company): ProviderAvailability
    {
        return ProviderAvailability::firstOrCreate(
            ['company_id' => $company->id],
            [
                'is_online' => (bool) $company->is_online,
                'is_24_7' => false,
                'max_concurrent_jobs' => 1,
                'active_jobs_count' => 0,
                'auto_accept' => false,
                'accept_timeout_seconds' => (int) config('locknear.dispatch.offer_ttl_seconds', 60),
                'weekly_hours' => $this->defaultWeeklyHours(),
                'last_seen_at' => $company->last_seen_at,
            ],
        );
    }

    private function defaultWeeklyHours(): array
    {
        return [
            'monday' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'],
            'tuesday' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'],
            'wednesday' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'],
            'thursday' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'],
            'friday' => ['enabled' => true, 'open' => '08:00', 'close' => '18:00'],
            'saturday' => ['enabled' => false, 'open' => '09:00', 'close' => '15:00'],
            'sunday' => ['enabled' => false, 'open' => '09:00', 'close' => '15:00'],
        ];
    }

    private function payload(Company $company, ProviderAvailability $availability): array
    {
        return [
            'id' => $availability->id,
            'company_id' => $company->id,
            'is_online' => (bool) $availability->is_online,
            'availability_status' => $company->availabilityStatus(),
            'dispatch_eligible' => $company->isDispatchEligible(),
            'is_24_7' => (bool) $availability->is_24_7,
            'max_concurrent_jobs' => (int) $availability->max_concurrent_jobs,
            'active_jobs_count' => (int) $availability->active_jobs_count,
            'auto_accept' => (bool) $availability->auto_accept,
            'accept_timeout_seconds' => (int) $availability->accept_timeout_seconds,
            'weekly_hours' => $availability->weekly_hours ?? $this->defaultWeeklyHours(),
            'pricing_filters' => $availability->pricing_filters ?? [],
            'available_until' => $availability->available_until?->toISOString(),
            'last_seen_at' => $availability->last_seen_at?->toISOString() ?? $company->last_seen_at?->toISOString(),
            'profile_ready' => (bool) ($company->phone && $company->address),
            'phone' => $company->phone,
            'address' => $company->address,
        ];
    }
}
