<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;
use App\Models\MarketExpansionMetric;
use Carbon\CarbonInterface;

class CoverageEngine
{
    public function snapshotZip(string $zip, ?CarbonInterface $date = null, ?string $market = null): MarketExpansionMetric
    {
        $date ??= now();
        $companies = Company::query()->where('zip', $zip);
        $leads = Lead::query()->where('zip', $zip)->whereDate('created_at', $date->toDateString());

        $directory = (clone $companies)->count();
        $claimed = (clone $companies)->where('is_claimed', true)->count();
        $verified = (clone $companies)->where('is_verified', true)->count();
        $online = (clone $companies)->where('is_online', true)->count();
        $demand = (clone $leads)->count();
        $coverage = $this->coveragePercent($verified, $demand);

        return MarketExpansionMetric::updateOrCreate(
            [
                'period_date' => $date->toDateString(),
                'market' => $market ?? 'default',
                'zip' => $zip,
            ],
            [
                'city' => (clone $companies)->whereNotNull('city')->value('city'),
                'state' => (clone $companies)->whereNotNull('state')->value('state'),
                'directory_provider_count' => $directory,
                'claimed_provider_count' => $claimed,
                'verified_provider_count' => $verified,
                'online_provider_count' => $online,
                'booking_demand_count' => $demand,
                'estimated_daily_demand' => $demand,
                'coverage_percent' => $coverage,
                'recommendation' => $this->recommendation($coverage, $demand, $verified),
                'metadata' => [
                    'invite_more_providers' => max(0, (int) ceil($demand / 4) - $verified),
                ],
            ],
        );
    }

    public function snapshotCity(string $city, string $state, ?CarbonInterface $date = null, ?string $market = null): MarketExpansionMetric
    {
        $date ??= now();
        $companies = Company::query()->where('city', $city)->where('state', strtoupper($state));
        $leads = Lead::query()
            ->where('city', $city)
            ->where('state', strtoupper($state))
            ->whereDate('created_at', $date->toDateString());

        $directory = (clone $companies)->count();
        $claimed = (clone $companies)->where('is_claimed', true)->count();
        $verified = (clone $companies)->where('is_verified', true)->count();
        $online = (clone $companies)->where('is_online', true)->count();
        $demand = (clone $leads)->count();
        $coverage = $this->coveragePercent($verified, $demand);

        return MarketExpansionMetric::updateOrCreate(
            [
                'period_date' => $date->toDateString(),
                'market' => $market ?? strtolower($city),
                'city' => $city,
                'state' => strtoupper($state),
                'zip' => null,
            ],
            [
                'directory_provider_count' => $directory,
                'claimed_provider_count' => $claimed,
                'verified_provider_count' => $verified,
                'online_provider_count' => $online,
                'booking_demand_count' => $demand,
                'estimated_daily_demand' => $demand,
                'coverage_percent' => $coverage,
                'recommendation' => $this->recommendation($coverage, $demand, $verified),
                'metadata' => [
                    'invite_more_providers' => max(0, (int) ceil($demand / 4) - $verified),
                ],
            ],
        );
    }

    private function coveragePercent(int $verifiedProviders, int $dailyDemand): float
    {
        if ($dailyDemand <= 0) {
            return $verifiedProviders > 0 ? 100.0 : 0.0;
        }

        return round(min(100, ($verifiedProviders * 4 / $dailyDemand) * 100), 2);
    }

    private function recommendation(float $coverage, int $dailyDemand, int $verifiedProviders): string
    {
        if ($dailyDemand >= 20 && $coverage < 50) {
            return 'expand';
        }

        if ($dailyDemand >= 5 && $verifiedProviders < 3) {
            return 'invite';
        }

        if ($coverage >= 80) {
            return 'healthy';
        }

        return 'watch';
    }
}
