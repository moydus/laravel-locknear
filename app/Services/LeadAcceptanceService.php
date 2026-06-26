<?php

namespace App\Services;

use App\Events\DispatchStatusChanged;
use App\Exceptions\LeadBillingException;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\PaymentIntent;
use App\Models\ServiceJob;
use App\Support\LeadPricing;
use Illuminate\Support\Facades\DB;

class LeadAcceptanceService
{
    public function __construct(
        private LeadBillingService $billing,
        private DispatchService $dispatch,
    ) {}

    /**
     * @throws LeadBillingException
     */
    public function accept(Lead $lead, Company $company): LeadAssignment
    {
        return DB::transaction(function () use ($lead, $company) {
            $lead = Lead::query()->lockForUpdate()->findOrFail($lead->id);

            $winnerExists = $lead->assignments()
                ->whereIn('status', ['accepted', 'en_route', 'arrived'])
                ->where('company_id', '!=', $company->id)
                ->exists();

            if ($winnerExists || ($lead->status !== 'new' && $lead->status !== 'assigned')) {
                throw new LeadBillingException('Lead already accepted by another company.', 'already_taken');
            }

            $leadCost = LeadPricing::forService($lead->service_type);

            $assignment = LeadAssignment::firstOrNew([
                'lead_id' => $lead->id,
                'company_id' => $company->id,
            ]);

            if (in_array($assignment->status, ['accepted', 'en_route', 'arrived', 'completed'], true)) {
                return $assignment->load(['lead', 'company']);
            }

            $chargeId = $this->billing->chargeForAccept(
                $company,
                $lead,
                $leadCost,
                $assignment->stripe_charge_id,
            );

            $assignment->fill([
                'lead_cost' => $leadCost,
                'stripe_charge_id' => $chargeId ?? $assignment->stripe_charge_id,
                'status' => 'accepted',
                'responded_at' => $assignment->responded_at ?? now(),
                'accepted_at' => now(),
            ]);
            $assignment->save();

            $lead->update([
                'status' => 'assigned',
                'assigned_at' => $lead->assigned_at ?? now(),
            ]);

            $booking = Booking::where('lead_id', $lead->id)->first();
            if ($booking) {
                $booking->update([
                    'company_id' => $company->id,
                    'status' => 'accepted',
                ]);

                ServiceJob::firstOrCreate(
                    ['booking_id' => $booking->id],
                    [
                        'lead_id' => $lead->id,
                        'company_id' => $company->id,
                        'status' => 'accepted',
                        'accepted_at' => now(),
                    ],
                );

                PaymentIntent::where('booking_id', $booking->id)
                    ->whereNull('company_id')
                    ->update(['company_id' => $company->id]);
            }

            return $assignment->fresh(['lead', 'company']);
        });
    }

    public function notifyAfterAccept(Lead $lead, Company $company, LeadAssignment $assignment): void
    {
        broadcast(new DispatchStatusChanged($lead->fresh(), $company, 'accepted'));

        $this->dispatch->sendCustomerConfirmation($lead->fresh(), $company);

        $this->billing->sendProviderReceipt(
            $company,
            $lead->fresh(),
            (float) $assignment->lead_cost,
            $assignment->stripe_charge_id,
        );

        app(LeadMessageService::class)->postSystemMessage(
            $lead->fresh(),
            "{$company->name} accepted your request. You can message your locksmith here if you need to share details.",
            $company,
        );
    }
}
