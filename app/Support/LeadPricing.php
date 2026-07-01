<?php

namespace App\Support;

use App\Models\Company;

class LeadPricing
{
    /**
     * Provider-listed price for a service. Returns null when the company has not set one.
     */
    public static function forCompanyService(?Company $company, string $serviceType): ?float
    {
        if (!$company) {
            return null;
        }

        return $company->servicePriceFor($serviceType);
    }

    /**
     * Legacy platform reference amounts — only used for unclaimed directory outreach.
     */
    public static function forService(string $serviceType): float
    {
        return match ($serviceType) {
            'car-lockout', 'locked-keys-in-car' => 25.00,
            'car-key-replacement', 'ignition-repair' => 40.00,
            'emergency', 'emergency-locksmith', '24-hour-locksmith' => 30.00,
            'commercial' => 50.00,
            'house-lockout', 'lost-car-keys', 'key-fob-programming' => 35.00,
            default => 20.00,
        };
    }

    /**
     * Suggested starting prices shown during provider service setup (not charged automatically).
     *
     * @return array<string, float>
     */
    public static function suggestedStartingPrices(): array
    {
        return [
            'car-lockout' => 175.00,
            'locked-keys-in-car' => 175.00,
            'car-key-replacement' => 275.00,
            'house-lockout' => 150.00,
            'lock-rekey' => 125.00,
            'commercial' => 350.00,
            'emergency' => 225.00,
            '24-hour-locksmith' => 200.00,
            'emergency-locksmith' => 200.00,
            'lost-car-keys' => 250.00,
            'key-fob-programming' => 225.00,
            'ignition-repair' => 300.00,
        ];
    }
}
