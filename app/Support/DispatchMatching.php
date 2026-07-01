<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;

class DispatchMatching
{
    public static function applyToQuery(Builder $query, Lead $lead): Builder
    {
        $awayCutoff = now()->subMinutes(config('locknear.presence.away_minutes', 2));

        $query->where('is_active', true)
            ->where('is_online', true)
            ->where('last_seen_at', '>=', $awayCutoff)
            ->whereHas('services', fn ($q) =>
                $q->where('service_type', $lead->service_type)->where('is_active', true)
            )
            ->where(function ($q) use ($lead) {
                self::applyAreaMatch($q, $lead);
            });

        if (config('locknear.dispatch.require_subscription', false)) {
            $query->where(function ($q) {
                $q->whereHas('subscription', fn ($sub) =>
                    $sub->whereIn('status', ['active', 'trialing'])
                );
            });
        }

        return $query;
    }

    public static function countForLead(Lead $lead): int
    {
        return self::applyToQuery(Company::query(), $lead)->count();
    }

    public static function companyMatchesLead(Company $company, Lead $lead): bool
    {
        if (!$company->is_active || !$company->is_online) {
            return false;
        }

        if (!$company->isDispatchEligible()) {
            return false;
        }

        if (config('locknear.dispatch.require_subscription', false) && !$company->meetsDispatchBillingRequirements()) {
            return false;
        }

        if (!$company->services()->where('service_type', $lead->service_type)->where('is_active', true)->exists()) {
            return false;
        }

        return self::matchesArea($company, $lead);
    }

    private static function applyAreaMatch(Builder $query, Lead $lead): void
    {
        $query->where(function ($q) use ($lead) {
            $q->whereJsonContains('service_areas', $lead->zip)
                ->orWhere('zip', $lead->zip);

            if ($lead->city) {
                $city = strtolower(trim($lead->city));
                $q->orWhereRaw('LOWER(city) = ?', [$city])
                    ->orWhereJsonContains('service_areas', $lead->city);
            }

            if ($lead->city && $lead->state) {
                $q->orWhere(function ($inner) use ($lead) {
                    $inner->where('state', strtoupper($lead->state))
                        ->whereRaw('LOWER(city) = ?', [strtolower(trim($lead->city))]);
                });
            }

            if ($lead->latitude && $lead->longitude) {
                $lat = (float) $lead->latitude;
                $lng = (float) $lead->longitude;
                $radiusKm = (float) config('locknear.dispatch.match_radius_km', 80);

                $q->orWhere(function ($inner) use ($lat, $lng, $radiusKm) {
                    $inner->whereNotNull('latitude')
                        ->whereNotNull('longitude')
                        ->where('latitude', '!=', 0)
                        ->where('longitude', '!=', 0)
                        ->whereRaw(
                            '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) <= ?',
                            [$lat, $lng, $lat, $radiusKm],
                        );
                });
            }
        });
    }

    private static function matchesArea(Company $company, Lead $lead): bool
    {
        if ($company->zip === $lead->zip) {
            return true;
        }

        $areas = is_array($company->service_areas) ? $company->service_areas : [];
        if (in_array($lead->zip, $areas, true) || ($lead->city && in_array($lead->city, $areas, true))) {
            return true;
        }

        if ($lead->city && strtolower(trim((string) $company->city)) === strtolower(trim($lead->city))) {
            return true;
        }

        if (
            $lead->city
            && $lead->state
            && strtoupper((string) $company->state) === strtoupper($lead->state)
            && strtolower(trim((string) $company->city)) === strtolower(trim($lead->city))
        ) {
            return true;
        }

        if (
            $lead->latitude
            && $lead->longitude
            && self::usableCoordinate($company->latitude)
            && self::usableCoordinate($company->longitude)
        ) {
            $radiusKm = (float) config('locknear.dispatch.match_radius_km', 80);
            $distance = self::haversineKm(
                (float) $lead->latitude,
                (float) $lead->longitude,
                (float) $company->latitude,
                (float) $company->longitude,
            );

            return $distance <= $radiusKm;
        }

        return false;
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private static function usableCoordinate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $number = (float) $value;

        return is_finite($number) && abs($number) > 0.0001;
    }
}
