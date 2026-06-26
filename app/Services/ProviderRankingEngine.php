<?php

namespace App\Services;

use App\Contracts\ETAProvider;
use App\Models\Company;
use App\Models\Lead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProviderRankingEngine
{
    public function __construct(
        private DispatchRuleEngine $rules,
        private ETAProvider $etaProvider,
    ) {}

    public function score(Company $provider, Lead $lead, ?array $strategy = null): array
    {
        $strategy ??= $this->rules->activeStrategy();
        $distance = $this->distance($provider, $lead);
        $eta = $this->eta($provider, $lead);
        $quality = $this->quality($provider);
        $acceptance = $this->acceptanceRate($provider);
        $cancellation = $this->cancellationRate($provider);
        $availability = $this->capacity($provider) > 0 ? 100.0 : 0.0;

        $normalized = [
            'distance' => $distance === null ? 50.0 : max(0.0, 100.0 - min($distance, 50.0) * 2.0),
            'eta' => $eta === null ? 50.0 : max(0.0, 100.0 - min($eta, 60.0) * (100.0 / 60.0)),
            'quality' => $quality,
            'acceptance' => $acceptance,
            'cancellation' => max(0.0, 100.0 - $cancellation),
            'availability' => $availability,
        ];

        $weights = $strategy['weights'] ?? [];
        $score = 0.0;
        $weightTotal = 0.0;

        foreach ($normalized as $key => $value) {
            $weight = (float) ($weights[$key] ?? 0);
            if ($weight <= 0) {
                continue;
            }

            $score += $value * $weight;
            $weightTotal += $weight;
        }

        return [
            'score' => $weightTotal > 0 ? round($score / $weightTotal, 4) : 0.0,
            'distance_miles' => $distance,
            'eta_minutes' => $eta,
            'quality_score' => $quality,
            'acceptance_rate' => $acceptance,
            'cancellation_rate' => $cancellation,
            'capacity' => $this->capacity($provider),
            'breakdown' => $normalized,
            'strategy_version' => $strategy['version'] ?? null,
        ];
    }

    public function rank(Collection $providers, Lead $lead, ?array $strategy = null): Collection
    {
        return $providers
            ->map(fn (Company $provider) => [
                'provider' => $provider,
                'ranking' => $this->score($provider, $lead, $strategy),
            ])
            ->sortByDesc(fn (array $row) => $row['ranking']['score'])
            ->values();
    }

    public function distance(Company $provider, Lead $lead): ?float
    {
        return $this->etaProvider->distanceMiles($provider, $lead);
    }

    public function eta(Company $provider, Lead $lead): ?int
    {
        return $this->etaProvider->etaMinutes($provider, $lead);
    }

    public function quality(Company $provider): float
    {
        $metric = DB::table('provider_performance_metrics')
            ->where('company_id', $provider->id)
            ->latest('period_date')
            ->first();

        if ($metric?->quality_score !== null) {
            return (float) $metric->quality_score;
        }

        return min(100.0, max(0.0, ((float) $provider->rating) * 20));
    }

    public function acceptanceRate(Company $provider): float
    {
        return $this->latestMetric($provider, 'acceptance_rate') ?? 100.0;
    }

    public function cancellationRate(Company $provider): float
    {
        return $this->latestMetric($provider, 'cancellation_rate') ?? 0.0;
    }

    public function capacity(Company $provider): int
    {
        $availability = DB::table('provider_availability')
            ->where('company_id', $provider->id)
            ->first();

        if (!$availability) {
            return 1;
        }

        return max(0, (int) $availability->max_concurrent_jobs - (int) $availability->active_jobs_count);
    }

    private function latestMetric(Company $provider, string $column): ?float
    {
        $value = DB::table('provider_performance_metrics')
            ->where('company_id', $provider->id)
            ->latest('period_date')
            ->value($column);

        return $value === null ? null : (float) $value;
    }
}
