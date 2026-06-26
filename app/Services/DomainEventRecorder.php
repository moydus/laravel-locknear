<?php

namespace App\Services;

use App\Models\DomainEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DomainEventRecorder
{
    public function record(string $eventName, ?Model $aggregate = null, array $payload = [], array $context = []): ?DomainEvent
    {
        if (!DB::getSchemaBuilder()->hasTable('domain_events')) {
            return null;
        }

        $attributes = [
            'event_type' => $eventName,
            'booking_id' => $context['booking_id'] ?? null,
            'job_id' => $context['job_id'] ?? null,
            'company_id' => $context['company_id'] ?? ($payload['company_id'] ?? null),
            'user_id' => $context['user_id'] ?? null,
            'aggregate_type' => $aggregate ? $aggregate::class : ($context['aggregate_type'] ?? null),
            'aggregate_id' => $aggregate?->getKey() ?? ($context['aggregate_id'] ?? null),
            'payload' => $payload,
            'occurred_at' => $context['occurred_at'] ?? now(),
        ];

        if (Schema::hasColumn('domain_events', 'event_name')) {
            $attributes['event_name'] = $eventName;
        }

        if (Schema::hasColumn('domain_events', 'processing_status')) {
            $attributes['processing_status'] = 'pending';
        }

        return DomainEvent::create(Arr::where($attributes, fn ($value) => !is_null($value)));
    }
}
