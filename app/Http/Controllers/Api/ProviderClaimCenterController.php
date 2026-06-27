<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderClaimCenterController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'company' => [
                    'id' => $company->id,
                    'public_id' => $company->public_id,
                    'name' => $company->name,
                    'is_claimed' => (bool) $company->is_claimed,
                    'is_verified' => (bool) $company->is_verified,
                    'provider_status' => $company->provider_status?->value ?? $company->provider_status,
                    'lifecycle_status' => $company->lifecycle_status?->value ?? $company->lifecycle_status,
                    'source' => $company->source,
                    'source_last_synced_at' => $company->source_last_synced_at?->toISOString(),
                    'claimed_at' => $company->claimed_at?->toISOString(),
                ],
                'sources' => $company->sources()->latest('last_synced_at')->get(),
                'identities' => $company->identities()->latest()->get(),
                'claims' => $company->claims()->latest()->get(),
                'growth_score' => $company->growthScore,
            ],
        ]);
    }
}
