<?php

namespace App\Contracts;

use App\Models\Company;
use App\Models\Lead;

interface ETAProvider
{
    public function distanceMiles(Company $provider, Lead $lead): ?float;

    public function etaMinutes(Company $provider, Lead $lead): ?int;
}
