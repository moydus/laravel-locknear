<?php

namespace App\Services\ETA;

use App\Contracts\ETAProvider;
use App\Models\Company;
use App\Models\Lead;

class HaversineETAProvider implements ETAProvider
{
    public function distanceMiles(Company $provider, Lead $lead): ?float
    {
        if (!$provider->latitude || !$provider->longitude || !$lead->latitude || !$lead->longitude) {
            return null;
        }

        $earthMiles = 3958.7613;
        $providerLat = deg2rad((float) $provider->latitude);
        $leadLat = deg2rad((float) $lead->latitude);
        $deltaLat = deg2rad((float) $lead->latitude - (float) $provider->latitude);
        $deltaLng = deg2rad((float) $lead->longitude - (float) $provider->longitude);

        $a = sin($deltaLat / 2) ** 2
            + cos($providerLat) * cos($leadLat) * sin($deltaLng / 2) ** 2;

        return round($earthMiles * 2 * atan2(sqrt($a), sqrt(1 - $a)), 2);
    }

    public function etaMinutes(Company $provider, Lead $lead): ?int
    {
        $distance = $this->distanceMiles($provider, $lead);
        if ($distance === null) {
            return null;
        }

        return max(5, (int) ceil(($distance / 22) * 60));
    }
}
