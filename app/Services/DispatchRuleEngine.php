<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DispatchRuleEngine
{
    public function activeStrategy(): array
    {
        $strategy = DB::table('dispatch_strategies')
            ->where('is_active', true)
            ->latest('id')
            ->first();

        if (!$strategy) {
            return $this->configStrategy();
        }

        return [
            'id' => $strategy->id,
            'version' => $strategy->version,
            'name' => $strategy->name,
            'max_parallel_offers' => (int) $strategy->max_parallel_offers,
            'offer_ttl_seconds' => (int) $strategy->offer_ttl_seconds,
            'weights' => [
                'distance' => (float) $strategy->distance_weight,
                'eta' => (float) $strategy->eta_weight,
                'quality' => (float) $strategy->rating_weight,
                'acceptance' => (float) $strategy->acceptance_rate_weight,
                'cancellation' => (float) $strategy->cancellation_rate_weight,
                'availability' => (float) $strategy->availability_weight,
            ],
            'rules' => $strategy->rules ? json_decode($strategy->rules, true) : [],
        ];
    }

    public function configStrategy(): array
    {
        return [
            'id' => null,
            'version' => 'config:' . config('locknear.dispatch.strategy', 'hybrid'),
            'name' => config('locknear.dispatch.strategy', 'hybrid'),
            'max_parallel_offers' => (int) config('locknear.dispatch.max_parallel_offers', 3),
            'offer_ttl_seconds' => (int) config('locknear.dispatch.offer_ttl_seconds', 60),
            'weights' => config('locknear.dispatch.weights', []),
            'rules' => [],
        ];
    }
}
