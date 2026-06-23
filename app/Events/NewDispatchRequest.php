<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\Company;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewDispatchRequest implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Lead $lead,
        public Company $company,
        public string $acceptToken,
        public string $rejectToken,
        public ?\Illuminate\Support\Carbon $expiresAt = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('company.' . $this->company->id)];
    }

    public function broadcastAs(): string
    {
        return 'dispatch.new';
    }

    public function broadcastWith(): array
    {
        return [
            'lead_id'      => $this->lead->id,
            'service'      => $this->lead->service_type,
            'city'         => $this->lead->city,
            'state'        => $this->lead->state,
            'zip'          => $this->lead->zip,
            'lat'          => $this->lead->latitude,
            'lng'          => $this->lead->longitude,
            'description'  => $this->lead->description,
            'accept_token' => $this->acceptToken,
            'reject_token' => $this->rejectToken,
            'expires_at'   => ($this->expiresAt ?? now()->addMinutes(config('locknear.dispatch.accept_token_minutes', 30)))->toISOString(),
        ];
    }
}
