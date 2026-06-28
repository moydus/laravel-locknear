<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DomainEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderMonitoringController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['data' => []]);
        }

        $availability = $company->providerAvailability;
        $coverage = $company->providerServiceAreas()
            ->where('is_active', true)
            ->latest('version')
            ->first();
        $payout = $company->payoutAccount;
        $metrics = DB::table('provider_performance_metrics')
            ->where('company_id', $company->id)
            ->latest('period_date')
            ->first();

        $items = collect([
            [
                'id' => 'dispatch',
                'name' => 'Dispatch Engine',
                'status' => $company->isDispatchEligible() ? 'healthy' : 'attention',
                'detail' => $company->isDispatchEligible()
                    ? 'Eligible for new dispatch offers'
                    : 'Complete profile, services, coverage, and availability',
                'metric' => $this->formatSeconds($metrics?->average_response_seconds),
            ],
            [
                'id' => 'stripe',
                'name' => 'Stripe Connect',
                'status' => ($payout?->charges_enabled && $payout?->payouts_enabled) ? 'healthy' : 'attention',
                'detail' => ($payout?->charges_enabled && $payout?->payouts_enabled)
                    ? 'Charges and payouts enabled'
                    : 'Stripe setup needs attention',
                'metric' => $payout?->payouts_enabled ? 'Ready' : 'Setup',
            ],
            [
                'id' => 'coverage',
                'name' => 'Coverage Engine',
                'status' => $coverage ? 'healthy' : 'attention',
                'detail' => $coverage
                    ? trim(collect([$coverage->city, $coverage->state, $coverage->zip])->filter()->join(', ')) ?: 'Coverage area active'
                    : 'Set service area before going online',
                'metric' => $coverage ? "{$coverage->radius_miles} mi" : 'Missing',
            ],
            [
                'id' => 'availability',
                'name' => 'Provider Availability',
                'status' => ($availability?->is_online ?? false) ? 'healthy' : 'attention',
                'detail' => ($availability?->is_online ?? false)
                    ? 'Provider is online'
                    : 'Provider is offline',
                'metric' => $availability?->availability_status ?? 'offline',
            ],
        ]);

        return response()->json([
            'data' => [
                'items' => $items,
                'summary' => [
                    'total' => $items->count(),
                    'healthy' => $items->where('status', 'healthy')->count(),
                    'attention' => $items->where('status', 'attention')->count(),
                    'last_activity_at' => DomainEvent::query()
                        ->where('company_id', $company->id)
                        ->latest('occurred_at')
                        ->value('occurred_at'),
                ],
            ],
        ]);
    }

    private function formatSeconds(?int $seconds): string
    {
        if (!$seconds) {
            return 'No data';
        }

        return $seconds < 60 ? "{$seconds}s" : round($seconds / 60, 1) . 'm';
    }
}
