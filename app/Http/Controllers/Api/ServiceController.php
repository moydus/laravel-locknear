<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    const ALLOWED = [
        'car-lockout', 'car-key-replacement', 'house-lockout', 'lock-rekey',
        'commercial', 'emergency', '24-hour-locksmith', 'emergency-locksmith',
        'locked-keys-in-car', 'lost-car-keys', 'key-fob-programming', 'ignition-repair',
    ];

    // GET /services
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $company->services()->get(['id', 'service_type', 'price', 'is_active']),
        ]);
    }

    // PUT /services  — aktif servisleri toplu güncelle (sync)
    public function sync(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company'], 404);
        }

        $validated = $request->validate([
            'services'             => ['required', 'array'],
            'services.*.type'      => ['required', 'string', 'in:' . implode(',', self::ALLOWED)],
            'services.*.is_active' => ['required', 'boolean'],
            'services.*.price'     => ['nullable', 'numeric', 'min:25', 'max:10000'],
        ]);

        foreach ($validated['services'] as $index => $svc) {
            if (($svc['is_active'] ?? false) && (!isset($svc['price']) || (float) $svc['price'] <= 0)) {
                return response()->json([
                    'message' => 'Each enabled service needs a listed price of at least $25.',
                    'errors' => [
                        "services.{$index}.price" => ['Enter your flat rate for this service.'],
                    ],
                ], 422);
            }
        }

        foreach ($validated['services'] as $svc) {
            CompanyService::updateOrCreate(
                ['company_id' => $company->id, 'service_type' => $svc['type']],
                ['is_active' => $svc['is_active'], 'price' => $svc['price'] ?? null]
            );
        }

        return response()->json([
            'data' => $company->services()->get(['id', 'service_type', 'price', 'is_active']),
        ]);
    }
}
