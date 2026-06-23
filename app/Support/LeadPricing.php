<?php

namespace App\Support;

class LeadPricing
{
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
}
