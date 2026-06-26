<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DispatchEngine
{
    public function __construct(
        private DispatchRuleEngine $rules,
        private ProviderRankingEngine $ranking,
        private DispatchService $legacyDispatch,
        private DomainEventRecorder $events,
    ) {}

    public function dispatch(Lead $lead): int
    {
        $strategy = $this->rules->activeStrategy();
        $this->recordEvent('BookingCreated', $lead);
        $this->recordEvent('DispatchStarted', $lead, ['strategy' => $strategy['version']]);

        return $this->legacyDispatch->dispatch($lead, (int) $strategy['max_parallel_offers']);
    }

    public function offerProviders(Lead $lead, Collection $providers): Collection
    {
        return $this->ranking->rank($providers, $lead, $this->rules->activeStrategy());
    }

    public function accept(Lead $lead, Company $provider): void
    {
        $this->recordEvent('ProviderAccepted', $lead, ['company_id' => $provider->id]);
    }

    public function decline(Lead $lead, Company $provider): void
    {
        $this->recordEvent('ProviderDeclined', $lead, ['company_id' => $provider->id]);
    }

    public function redispatch(Lead $lead, array $context = []): int
    {
        $this->recordEvent('DispatchRedispatched', $lead, $context);

        return $this->dispatch($lead);
    }

    public function expire(Lead $lead, array $context = []): void
    {
        $this->recordEvent('DispatchExpired', $lead, $context);
    }

    public function complete(Lead $lead, Company $provider): void
    {
        $this->recordEvent('DispatchCompleted', $lead, ['company_id' => $provider->id]);
    }

    private function recordEvent(string $type, Lead $lead, array $payload = []): void
    {
        $this->events->record($type, $lead, $payload, [
            'booking_id' => DB::table('bookings')->where('lead_id', $lead->id)->value('id'),
            'company_id' => $payload['company_id'] ?? null,
            'user_id' => $lead->user_id,
        ]);
    }
}
