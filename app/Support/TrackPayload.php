<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadAssignment;

class TrackPayload
{
    public static function forLead(Lead $lead): array
    {
        $assignment = self::resolveActiveAssignment($lead);
        $dispatch = self::dispatchMeta($lead, $assignment);

        return [
            'status' => $lead->status,
            'work_order_number' => $lead->work_order_number,
            'service' => $lead->service_type,
            'zip' => $lead->zip,
            'city' => $lead->city,
            'state' => $lead->state,
            'customer_lat' => $lead->latitude,
            'customer_lng' => $lead->longitude,
            'dispatch_fee_cents' => (int) ($lead->dispatch_fee_cents ?? 0),
            'dispatch_fee_acknowledged' => (bool) $lead->dispatch_fee_acknowledged,
            'assigned' => $assignment ? self::assignedBlock($lead, $assignment) : null,
            'dispatch' => $dispatch,
            'nearby_providers' => $assignment ? [] : self::nearestDirectoryCompanies($lead),
        ];
    }

    public static function dispatchMeta(Lead $lead, ?LeadAssignment $assignment = null): array
    {
        $assignment ??= self::resolveActiveAssignment($lead);

        if ($assignment) {
            return [
                'phase' => 'matched',
                'label' => 'On the way',
                'message' => "{$assignment->company?->name} is handling your request.",
                'live_providers_nearby' => null,
                'providers_contacted' => null,
                'directory_nearby' => null,
            ];
        }

        $lead->loadMissing('assignments');
        $pending = $lead->assignments->where('status', 'pending')->count();
        $liveNearby = self::countLiveProviders($lead);
        $directoryNearby = self::nearestDirectoryCompanies($lead, 1);

        if ($pending > 0) {
            return [
                'phase' => 'contacting',
                'label' => 'Contacting locksmiths',
                'message' => "We're contacting {$pending} verified locksmith" . ($pending === 1 ? '' : 's') . ' near you.',
                'live_providers_nearby' => $liveNearby,
                'providers_contacted' => $pending,
                'directory_nearby' => count($directoryNearby),
            ];
        }

        if ($liveNearby === 0) {
            $directoryCount = count(self::nearestDirectoryCompanies($lead));
            $cityLabel = trim(($lead->city ?: 'your area') . ($lead->state ? ", {$lead->state}" : ''));

            return [
                'phase' => 'no_live_providers',
                'label' => 'Matching your request',
                'message' => $directoryCount > 0
                    ? "No locksmiths are online in {$cityLabel} right now. We're notifying nearby listings and will text you when someone accepts."
                    : "No locksmiths are available in {$cityLabel} yet. We'll email you when coverage expands.",
                'live_providers_nearby' => 0,
                'providers_contacted' => 0,
                'directory_nearby' => $directoryCount,
            ];
        }

        return [
            'phase' => 'searching',
            'label' => 'Searching',
            'message' => 'Finding an available locksmith near your location…',
            'live_providers_nearby' => $liveNearby,
            'providers_contacted' => 0,
            'directory_nearby' => count($directoryNearby),
        ];
    }

    public static function resolveActiveAssignment(Lead $lead): ?LeadAssignment
    {
        $assignment = $lead->assignments()
            ->where('status', 'accepted')
            ->with('company')
            ->first();

        if ($assignment) {
            return $assignment;
        }

        return $lead->assignments()
            ->whereIn('status', ['en_route', 'arrived', 'completed', 'unable_to_verify'])
            ->latest('updated_at')
            ->with('company')
            ->first();
    }

    public static function assignedBlock(Lead $lead, LeadAssignment $assignment): array
    {
        $assignment->loadMissing('company');

        $lat = $assignment->provider_latitude ?? $assignment->company?->latitude;
        $lng = $assignment->provider_longitude ?? $assignment->company?->longitude;

        return [
            'company_name' => $assignment->company?->name,
            'company_phone' => $assignment->company?->phone,
            'status' => $assignment->status,
            'lat' => self::usableCoordinate($lat) ? (float) $lat : null,
            'lng' => self::usableCoordinate($lng) ? (float) $lng : null,
            'last_location_at' => optional($assignment->last_location_at)?->toIso8601String(),
            'eta' => DispatchEta::estimateMinutes($lead, $assignment),
            'service_refusal_reason' => $assignment->service_refusal_reason,
            'service_refused_at' => optional($assignment->service_refused_at)?->toIso8601String(),
            'dispatch_fee_eligible' => $assignment->dispatch_fee_eligible,
            'dispatch_fee_capture_status' => $assignment->dispatch_fee_capture_status,
            'dispatch_fee_capture_amount_cents' => $assignment->dispatch_fee_capture_amount_cents,
        ];
    }

    public static function countLiveProviders(Lead $lead): int
    {
        $awayCutoff = now()->subMinutes(config('locknear.presence.away_minutes', 2));

        $query = Company::where('is_active', true)
            ->where('is_online', true)
            ->where('last_seen_at', '>=', $awayCutoff)
            ->whereHas('services', fn ($q) =>
                $q->where('service_type', $lead->service_type)->where('is_active', true)
            )
            ->where(function ($q) use ($lead) {
                $q->whereJsonContains('service_areas', $lead->zip)
                    ->orWhere('zip', $lead->zip);
            });

        if (config('locknear.dispatch.require_subscription', false)) {
            $query->whereHas('subscription', fn ($q) =>
                $q->whereIn('status', ['active', 'trialing'])
            );
        }

        return $query->count();
    }

    /**
     * @return array<int, array{name: string, lat: float, lng: float, distance_km: float|null, is_online: bool, is_claimed: bool}>
     */
    public static function nearestDirectoryCompanies(Lead $lead, int $limit = 5): array
    {
        $query = Company::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->where('latitude', '!=', 0)
            ->where('longitude', '!=', 0);

        if ($lead->latitude && $lead->longitude) {
            $lat = (float) $lead->latitude;
            $lng = (float) $lead->longitude;
            $query->selectRaw(
                'companies.*, (6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude)))) AS distance_km',
                [$lat, $lng, $lat],
            )->orderBy('distance_km');
        } else {
            $query->where(function ($q) use ($lead) {
                $q->whereJsonContains('service_areas', $lead->zip)
                    ->orWhere('zip', $lead->zip);
            });
        }

        return $query
            ->limit($limit * 3)
            ->get()
            ->filter(function (Company $company) use ($lead) {
                if (!$lead->latitude || !$lead->longitude) {
                    return true;
                }

                if (!self::usableCoordinate($company->latitude) || !self::usableCoordinate($company->longitude)) {
                    return false;
                }

                $distanceKm = isset($company->distance_km)
                    ? (float) $company->distance_km
                    : null;

                return $distanceKm === null || $distanceKm <= 120;
            })
            ->take($limit)
            ->map(fn (Company $company) => [
                'name' => $company->name,
                'lat' => (float) $company->latitude,
                'lng' => (float) $company->longitude,
                'distance_km' => isset($company->distance_km) ? round((float) $company->distance_km, 1) : null,
                'is_online' => (bool) $company->is_online,
                'is_claimed' => (bool) $company->is_claimed,
            ])
            ->values()
            ->all();
    }

    private static function usableCoordinate(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            return false;
        }

        return abs($number) > 0.0001;
    }
}
