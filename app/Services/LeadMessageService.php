<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadMessage;

class LeadMessageService
{
    public function canExchangeMessages(Lead $lead): bool
    {
        if (in_array($lead->status, ['cancelled'], true)) {
            return false;
        }

        return $lead->assignments()
            ->whereIn('status', ['accepted', 'en_route', 'arrived', 'completed'])
            ->exists();
    }

    public function activeCompany(Lead $lead): ?Company
    {
        $assignment = $lead->assignments()
            ->whereIn('status', ['accepted', 'en_route', 'arrived', 'completed'])
            ->latest('updated_at')
            ->with('company')
            ->first();

        return $assignment?->company;
    }

    public function postSystemMessage(Lead $lead, string $body, ?Company $company = null): LeadMessage
    {
        return LeadMessage::create([
            'lead_id' => $lead->id,
            'company_id' => $company?->id,
            'sender' => 'system',
            'body' => $body,
        ]);
    }

    public function postCustomerMessage(Lead $lead, string $body): LeadMessage
    {
        $company = $this->activeCompany($lead);

        return LeadMessage::create([
            'lead_id' => $lead->id,
            'company_id' => $company?->id,
            'sender' => 'customer',
            'body' => trim($body),
        ]);
    }

    public function postProviderMessage(Lead $lead, Company $company, string $body): LeadMessage
    {
        return LeadMessage::create([
            'lead_id' => $lead->id,
            'company_id' => $company->id,
            'sender' => 'provider',
            'body' => trim($body),
        ]);
    }

    public function assertProviderAssignment(Lead $lead, Company $company): LeadAssignment
    {
        $assignment = $company->leads()
            ->where('lead_id', $lead->id)
            ->whereIn('status', ['accepted', 'en_route', 'arrived', 'completed', 'pending'])
            ->first();

        if (!$assignment) {
            abort(404, 'Assignment not found');
        }

        return $assignment;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function serializeThread(Lead $lead): array
    {
        return $lead->messages()
            ->latest()
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->values()
            ->map(fn (LeadMessage $message) => [
                'id' => $message->id,
                'sender' => $message->sender,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
