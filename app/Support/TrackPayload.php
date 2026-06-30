<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\LeadAssignment;

class TrackPayload
{
    public static function forLead(Lead $lead): array
    {
        $assignment = self::resolveActiveAssignment($lead);

        return [
            'status' => $lead->status,
            'work_order_number' => $lead->work_order_number,
            'service' => $lead->service_type,
            'zip' => $lead->zip,
            'city' => $lead->city,
            'state' => $lead->state,
            'customer_lat' => $lead->latitude,
            'customer_lng' => $lead->longitude,
            'assigned' => $assignment ? self::assignedBlock($lead, $assignment) : null,
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

        return [
            'company_name' => $assignment->company?->name,
            'company_phone' => $assignment->company?->phone,
            'status' => $assignment->status,
            'lat' => $assignment->provider_latitude ?? $assignment->company?->latitude,
            'lng' => $assignment->provider_longitude ?? $assignment->company?->longitude,
            'last_location_at' => optional($assignment->last_location_at)?->toIso8601String(),
            'eta' => DispatchEta::estimateMinutes($lead, $assignment),
            'service_refusal_reason' => $assignment->service_refusal_reason,
            'dispatch_fee_eligible' => $assignment->dispatch_fee_eligible,
            'dispatch_fee_capture_amount_cents' => $assignment->dispatch_fee_capture_amount_cents,
        ];
    }
}
