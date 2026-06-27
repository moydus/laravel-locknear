<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProviderMetricsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['data' => []]);
        }

        $days = min(max((int) $request->integer('days', 30), 1), 365);
        $since = now()->subDays($days - 1)->toDateString();

        $rows = DB::table('provider_performance_metrics')
            ->where('company_id', $company->id)
            ->where('period_date', '>=', $since)
            ->orderBy('period_date')
            ->get();

        $latest = $rows->last();

        return response()->json([
            'data' => [
                'latest' => $latest,
                'series' => $rows,
                'summary' => [
                    'offers_sent' => (int) $rows->sum('offers_sent'),
                    'offers_accepted' => (int) $rows->sum('offers_accepted'),
                    'jobs_completed' => (int) $rows->sum('jobs_completed'),
                    'jobs_cancelled' => (int) $rows->sum('jobs_cancelled'),
                    'acceptance_rate' => $this->weightedRate($rows->sum('offers_accepted'), $rows->sum('offers_sent')),
                    'completion_rate' => $this->weightedRate($rows->sum('jobs_completed'), $rows->sum('jobs_completed') + $rows->sum('jobs_cancelled')),
                    'average_response_seconds' => round((float) $rows->avg('average_response_seconds')),
                    'average_eta_minutes' => round((float) $rows->avg('average_eta_minutes')),
                    'quality_score' => round((float) ($latest->quality_score ?? 0), 2),
                ],
            ],
        ]);
    }

    private function weightedRate(float|int $numerator, float|int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 2) : 0.0;
    }
}
