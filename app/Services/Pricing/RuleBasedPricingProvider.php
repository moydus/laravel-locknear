<?php

namespace App\Services\Pricing;

use App\Contracts\PricingProvider;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class RuleBasedPricingProvider implements PricingProvider
{
    public function estimate(Lead $lead): array
    {
        $rule = $this->ruleFor($lead);
        $base = $rule ? $this->fromRule($rule) : $this->fallback($lead->service_type);
        $surge = $this->surge($lead);

        $estimatedMin = (int) round($base['estimated_min_cents'] * $surge);
        $estimatedMax = (int) round($base['estimated_max_cents'] * $surge);
        $authorization = $base['authorization_cents'] ?? $estimatedMax;

        return [
            'service_type' => $lead->service_type,
            'currency' => $base['currency'],
            'minimum_cents' => $base['minimum_cents'],
            'estimated_min_cents' => $estimatedMin,
            'estimated_max_cents' => $estimatedMax,
            'authorization_cents' => max($authorization, $estimatedMin),
            'commission_rate' => $base['commission_rate'],
            'algorithm_version' => $base['algorithm_version'],
            'surge_multiplier' => $surge,
            'pricing_rule_id' => $rule?->id,
        ];
    }

    public function surge(Lead $lead): float
    {
        if (!config('locknear.pricing.surge_enabled', false)) {
            return 1.0;
        }

        $snapshot = DB::table('market_demand_snapshots')
            ->where('zip', $lead->zip)
            ->where('window_start', '>=', now()->subHour())
            ->latest('window_start')
            ->first();

        if (!$snapshot || (int) $snapshot->online_provider_count <= 0) {
            return 1.0;
        }

        $pressure = (int) $snapshot->request_count / max(1, (int) $snapshot->online_provider_count);

        return round(min(2.0, max(1.0, 1 + (($pressure - 2) * 0.10))), 2);
    }

    private function ruleFor(Lead $lead): ?object
    {
        return DB::table('pricing_rules')
            ->join('service_types', 'service_types.id', '=', 'pricing_rules.service_type_id')
            ->where('service_types.slug', $lead->service_type)
            ->where('pricing_rules.is_active', true)
            ->where(function ($query) use ($lead) {
                $query->where('pricing_rules.zip', $lead->zip)
                    ->orWhere(function ($query) use ($lead) {
                        $query->whereNull('pricing_rules.zip')
                            ->where('pricing_rules.city', $lead->city)
                            ->where('pricing_rules.state', $lead->state);
                    })
                    ->orWhere(function ($query) use ($lead) {
                        $query->whereNull('pricing_rules.zip')
                            ->whereNull('pricing_rules.city')
                            ->where('pricing_rules.state', $lead->state);
                    })
                    ->orWhere(function ($query) {
                        $query->whereNull('pricing_rules.zip')
                            ->whereNull('pricing_rules.city')
                            ->whereNull('pricing_rules.state');
                    });
            })
            ->select('pricing_rules.*')
            ->orderByRaw('pricing_rules.zip IS NULL')
            ->orderByRaw('pricing_rules.city IS NULL')
            ->orderByRaw('pricing_rules.state IS NULL')
            ->latest('pricing_rules.id')
            ->first();
    }

    private function fromRule(object $rule): array
    {
        return [
            'minimum_cents' => (int) ($rule->minimum_amount_cents ?? round(((float) $rule->minimum_amount) * 100)),
            'estimated_min_cents' => (int) ($rule->estimated_min_amount_cents ?? round(((float) $rule->estimated_min_amount) * 100)),
            'estimated_max_cents' => (int) ($rule->estimated_max_amount_cents ?? round(((float) $rule->estimated_max_amount) * 100)),
            'authorization_cents' => $rule->authorization_amount_cents ? (int) $rule->authorization_amount_cents : null,
            'currency' => $rule->currency ?? config('locknear.pricing.default_currency', 'usd'),
            'commission_rate' => (float) $rule->commission_rate,
            'algorithm_version' => $rule->algorithm_version ?? 'v1',
        ];
    }

    private function fallback(string $serviceType): array
    {
        $ranges = [
            'car-lockout' => [8000, 14000],
            'locked-keys-in-car' => [8000, 14000],
            'house-lockout' => [9000, 16000],
            'car-key-replacement' => [14000, 30000],
            'lost-car-keys' => [14000, 30000],
            'key-fob-programming' => [12000, 26000],
            'ignition-repair' => [18000, 36000],
            'commercial' => [14000, 35000],
            'emergency' => [12000, 24000],
            '24-hour-locksmith' => [12000, 24000],
        ];

        [$min, $max] = $ranges[$serviceType] ?? [9000, 18000];

        return [
            'minimum_cents' => $min,
            'estimated_min_cents' => $min,
            'estimated_max_cents' => $max,
            'authorization_cents' => $max,
            'currency' => config('locknear.pricing.default_currency', 'usd'),
            'commission_rate' => (float) config('locknear.pricing.default_commission_rate', 0.20),
            'algorithm_version' => 'fallback-v1',
        ];
    }
}
