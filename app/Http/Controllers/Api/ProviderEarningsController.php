<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentTransaction;
use App\Models\Payout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderEarningsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['data' => []]);
        }

        $transactions = PaymentTransaction::where('company_id', $company->id)
            ->latest('processed_at')
            ->latest()
            ->paginate(25);

        $payouts = Payout::where('company_id', $company->id)
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'summary' => [
                    'gross_amount_cents' => (int) PaymentTransaction::where('company_id', $company->id)->sum('gross_amount_cents'),
                    'fee_amount_cents' => (int) PaymentTransaction::where('company_id', $company->id)->sum('fee_amount_cents'),
                    'net_amount_cents' => (int) PaymentTransaction::where('company_id', $company->id)->sum('net_amount_cents'),
                    'pending_payout_cents' => (int) Payout::where('company_id', $company->id)->whereIn('status', ['pending', 'scheduled'])->sum('net_amount_cents'),
                ],
                'transactions' => $transactions,
                'payouts' => $payouts,
            ],
        ]);
    }
}
