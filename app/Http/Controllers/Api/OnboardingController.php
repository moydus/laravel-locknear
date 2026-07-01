<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
  private const BUSINESS_SERVICES = [
    'residential' => ['house-lockout', 'lock-rekey', 'emergency'],
    'commercial' => ['commercial', 'lock-rekey'],
    'automotive' => ['car-lockout', 'car-key-replacement', 'key-fob-programming'],
    'mobile-24-7' => ['house-lockout', 'car-lockout', 'commercial', 'emergency', '24-hour-locksmith'],
  ];

  public function store(Request $request): JsonResponse
  {
    $company = $request->user()->company;

    if (!$company) {
      return response()->json(['error' => 'No company found'], 404);
    }

    $validated = $request->validate([
      'step' => ['required', 'integer', 'in:1,2'],
      'business_type' => ['required_if:step,1', 'string', 'in:residential,commercial,automotive,mobile-24-7'],
      'phone' => ['required_if:step,2', 'string', 'max:20'],
      'city' => ['required_if:step,2', 'string', 'max:100'],
      'state' => ['required_if:step,2', 'string', 'max:100'],
      'zip' => ['required_if:step,2', 'string', 'max:20'],
      'address' => ['nullable', 'string', 'max:255'],
      'formatted_address' => ['nullable', 'string', 'max:255'],
      'google_place_id' => ['nullable', 'string', 'max:255'],
      'place_source' => ['nullable', 'string', 'max:50'],
      'latitude' => ['nullable', 'numeric', 'between:-90,90'],
      'longitude' => ['nullable', 'numeric', 'between:-180,180'],
    ]);

    if ((int) $validated['step'] === 1) {
      $company->update(['business_type' => $validated['business_type']]);
      $this->syncDefaultServices($company, $validated['business_type']);

      return response()->json([
        'data' => $this->companyPayload($company->fresh()),
      ]);
    }

    $company->update([
      'phone' => $validated['phone'],
      'address' => $validated['address'] ?? $validated['formatted_address'] ?? trim("{$validated['city']}, {$validated['state']} {$validated['zip']}"),
      'city' => $validated['city'],
      'state' => $validated['state'],
      'zip' => $validated['zip'],
      'formatted_address' => $validated['formatted_address'] ?? $validated['address'] ?? trim("{$validated['city']}, {$validated['state']} {$validated['zip']}"),
      'google_place_id' => $validated['google_place_id'] ?? null,
      'place_source' => $validated['place_source'] ?? null,
      'place_verified_at' => isset($validated['google_place_id']) ? now() : null,
      'latitude' => $validated['latitude'] ?? null,
      'longitude' => $validated['longitude'] ?? null,
      'service_areas' => [$validated['zip']],
      'onboarding_completed_at' => now(),
      'is_active' => true,
    ]);

    return response()->json([
      'data' => $this->companyPayload($company->fresh()),
    ]);
  }

  public function complete(Request $request): JsonResponse
  {
    $company = $request->user()->company;

    if (!$company) {
      return response()->json(['error' => 'No company found'], 404);
    }

    if (!$company->onboarding_completed_at) {
      $company->update(['onboarding_completed_at' => now()]);
    }

    return response()->json([
      'data' => $this->companyPayload($company->fresh()),
    ]);
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

  private function companyPayload(Company $company): array
  {
    return [
      'id' => $company->id,
      'name' => $company->name,
      'business_type' => $company->business_type,
      'onboarding_completed_at' => $company->onboarding_completed_at?->toIso8601String(),
      'phone' => $company->phone,
      'address' => $company->address,
      'formatted_address' => $company->formatted_address,
      'google_place_id' => $company->google_place_id,
      'place_source' => $company->place_source,
      'latitude' => $company->latitude,
      'longitude' => $company->longitude,
      'city' => $company->city,
      'state' => $company->state,
      'zip' => $company->zip,
    ];
  }
}
