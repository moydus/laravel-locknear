<?php

namespace App\Events;

use App\Models\Lead;
use App\Models\Company;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DispatchStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    // Status transitions: accepted → en_route → arrived → completed
    public function __construct(
        public Lead $lead,
        public Company $company,
        public string $status,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('lead.' . $this->lead->customer_token)];
    }

    public function broadcastAs(): string
    {
        return 'status.changed';
    }

    public function broadcastWith(): array
    {
        $lat = $this->company->latitude;
        $lng = $this->company->longitude;
        $hasCoords = is_numeric($lat) && is_numeric($lng)
            && abs((float) $lat) > 0.0001
            && abs((float) $lng) > 0.0001;

        return [
            'status'       => $this->status,
            'company_name' => $this->company->name,
            'company_phone'=> $this->company->phone,
            'lat'          => $hasCoords ? (float) $lat : null,
            'lng'          => $hasCoords ? (float) $lng : null,
        ];
    }
}
