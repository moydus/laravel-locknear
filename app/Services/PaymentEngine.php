<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;

class PaymentEngine
{
    public function __construct(private LeadBillingService $leadBilling) {}

    public function createIntent(array $payload): array
    {
        return ['status' => 'pending_integration', 'payload' => $payload];
    }

    public function authorize(array $payload): array
    {
        return ['status' => 'pending_integration', 'payload' => $payload];
    }

    public function capture(array $payload): array
    {
        return ['status' => 'pending_integration', 'payload' => $payload];
    }

    public function refund(array $payload): array
    {
        return ['status' => 'pending_integration', 'payload' => $payload];
    }

    public function payout(array $payload): array
    {
        return ['status' => 'pending_integration', 'payload' => $payload];
    }

    public function chargeProviderLeadFee(Company $company, Lead $lead, float $amount, ?string $existingChargeId = null): ?string
    {
        return $this->leadBilling->chargeForAccept($company, $lead, $amount, $existingChargeId);
    }
}
