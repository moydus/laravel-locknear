<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\LeadAssignment;

class DispatchEta
{
    public const AVERAGE_SPEED_KMH = 28;

    public static function estimateMinutes(?Lead $lead, ?LeadAssignment $assignment): ?int
    {
        if (!$lead || !$assignment) {
            return null;
        }

        $assignment->loadMissing('company');

        $customerLat = (float) $lead->latitude;
        $customerLng = (float) $lead->longitude;
        $providerLat = (float) ($assignment->provider_latitude ?? $assignment->company?->latitude);
        $providerLng = (float) ($assignment->provider_longitude ?? $assignment->company?->longitude);

        if (!$customerLat || !$customerLng || !$providerLat || !$providerLng) {
            return in_array($assignment->status, ['accepted', 'en_route', 'arrived'], true) ? 15 : null;
        }

        $distanceKm = self::haversineKm($customerLat, $customerLng, $providerLat, $providerLng);
        $minutes = (int) round(($distanceKm / self::AVERAGE_SPEED_KMH) * 60);

        return max(1, min(90, $minutes));
    }

    public static function haversineKm(
        float $lat1,
        float $lng1,
        float $lat2,
        float $lng2,
    ): float {
        $toRad = fn (float $deg): float => $deg * M_PI / 180;
        $earthKm = 6371;
        $dLat = $toRad($lat2 - $lat1);
        $dLng = $toRad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos($toRad($lat1)) * cos($toRad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthKm * $c;
    }
}
