<?php

namespace App\Contracts;

use App\Models\Lead;

interface PricingProvider
{
    public function estimate(Lead $lead): array;

    public function surge(Lead $lead): float;
}
