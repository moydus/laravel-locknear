<?php

namespace App\Services;

use App\Contracts\PricingProvider;
use App\Models\Lead;

class PricingEngine
{
    public function __construct(private PricingProvider $provider) {}

    public function estimate(Lead $lead): array
    {
        return $this->provider->estimate($lead);
    }

    public function calculate(int $serviceTotalCents, ?float $commissionRate = null, int $tipCents = 0, int $taxCents = 0, int $discountCents = 0): array
    {
        $rate = $commissionRate ?? (float) config('locknear.pricing.default_commission_rate', 0.20);
        $platformFee = (int) round(max(0, $serviceTotalCents - $discountCents) * $rate);
        $providerAmount = max(0, $serviceTotalCents + $tipCents + $taxCents - $discountCents - $platformFee);

        return [
            'service_total_cents' => $serviceTotalCents,
            'platform_fee_cents' => $platformFee,
            'provider_amount_cents' => $providerAmount,
            'tax_amount_cents' => $taxCents,
            'tip_amount_cents' => $tipCents,
            'discount_amount_cents' => $discountCents,
            'commission_rate' => $rate,
        ];
    }

    public function surge(Lead $lead): float
    {
        return $this->provider->surge($lead);
    }

    public function fees(int $serviceTotalCents, ?float $commissionRate = null): array
    {
        return $this->calculate($serviceTotalCents, $commissionRate);
    }

    public function commission(int $serviceTotalCents, ?float $commissionRate = null): int
    {
        return $this->calculate($serviceTotalCents, $commissionRate)['platform_fee_cents'];
    }
}
