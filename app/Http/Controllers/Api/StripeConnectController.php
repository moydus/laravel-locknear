<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Stripe\Exception\ApiErrorException;

class StripeConnectController extends Controller
{
    public function __construct(private StripeConnectService $connect) {}

    public function status(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        try {
            $account = $this->connect->status($company);
        } catch (RuntimeException|ApiErrorException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->payload($account)]);
    }

    public function onboarding(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        try {
            $result = $this->connect->onboardingLink($company);
        } catch (RuntimeException|ApiErrorException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'url' => $result['url'],
                'account' => $this->payload($result['account']),
            ],
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        try {
            $url = $this->connect->loginLink($company);
        } catch (RuntimeException|ApiErrorException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['url' => $url]]);
    }

    private function payload($account): array
    {
        return [
            'id' => $account->id,
            'processor' => $account->processor,
            'stripe_account_id' => $account->stripe_account_id,
            'status' => $account->status,
            'stripe_connected' => (bool) $account->stripe_account_id,
            'verified' => (bool) $account->onboarded_at,
            'bank_added' => (bool) $account->payouts_enabled,
            'charges_enabled' => (bool) $account->charges_enabled,
            'payouts_enabled' => (bool) $account->payouts_enabled,
            'requirements' => $account->requirements ?? [],
            'metadata' => $account->metadata ?? [],
        ];
    }
}
