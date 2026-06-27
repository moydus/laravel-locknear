<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderServiceArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderCoverageController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $areas = $company->providerServiceAreas()
            ->latest('version')
            ->latest('id')
            ->get();

        return response()->json([
            'data' => [
                'active' => $areas->firstWhere('is_active', true),
                'history' => $areas->values(),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $validated = $request->validate([
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'zip' => ['nullable', 'string', 'max:20'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_miles' => ['required', 'integer', 'min:1', 'max:250'],
            'metadata' => ['nullable', 'array'],
        ]);

        $active = $company->providerServiceAreas()
            ->where('is_active', true)
            ->latest('version')
            ->first();

        if ($active) {
            $active->update([
                'is_active' => false,
                'retired_at' => now(),
            ]);
        }

        $area = ProviderServiceArea::create([
            'company_id' => $company->id,
            'version' => ($active?->version ?? 0) + 1,
            'is_active' => true,
            'effective_at' => now(),
            ...$validated,
        ]);

        $company->update(array_filter([
            'city' => $validated['city'] ?? null,
            'state' => $validated['state'] ?? null,
            'zip' => $validated['zip'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ], fn ($value) => $value !== null));

        return response()->json(['data' => $area->fresh()]);
    }
}
