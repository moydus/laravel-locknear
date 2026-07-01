<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;
use App\Support\DispatchMatching;

class OpenLeadDispatchService
{
    public function dispatchForCompany(Company $company): int
    {
        if (!$company->isDispatchEligible() || !$company->meetsDispatchBillingRequirements()) {
            return 0;
        }

        $dispatch = app(DispatchService::class);
        $offered = 0;

        Lead::query()
            ->where('status', 'new')
            ->where('created_at', '>=', now()->subHours(24))
            ->whereDoesntHave('assignments', fn ($q) => $q->where('status', 'accepted'))
            ->orderByDesc('created_at')
            ->limit(25)
            ->get()
            ->each(function (Lead $lead) use ($company, $dispatch, &$offered) {
                if (!DispatchMatching::companyMatchesLead($company, $lead)) {
                    return;
                }

                if ($dispatch->offerLeadToCompany($lead, $company)) {
                    $offered++;
                }
            });

        return $offered;
    }
}
