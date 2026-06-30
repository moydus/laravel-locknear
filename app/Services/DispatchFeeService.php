<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\PaymentIntent;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DispatchFeeService
{
    public function __construct(private PaymentEngine $payments) {}

    /**
     * Partial-capture the dispatch fee when a provider cannot verify ownership.
     * Stripe releases any uncaptured authorization remainder automatically.
     *
     * @return array{dispatch_fee_capture_status: ?string, dispatch_fee_capture_amount_cents: ?int}
     */
    public function captureForVerificationFailure(Lead $lead, LeadAssignment $assignment): array
    {
        $feeCents = (int) ($lead->dispatch_fee_cents ?? 0);

        if ($feeCents <= 0 || !$lead->dispatch_fee_acknowledged) {
            return [
                'dispatch_fee_capture_status' => null,
                'dispatch_fee_capture_amount_cents' => null,
            ];
        }

        $paymentIntent = PaymentIntent::where('lead_id', $lead->id)
            ->where('status', 'requires_capture')
            ->latest('id')
            ->first();

        if (!$paymentIntent) {
            Log::warning('Dispatch fee capture skipped — no authorized payment intent', [
                'lead_id' => $lead->id,
                'assignment_id' => $assignment->id,
            ]);

            return [
                'dispatch_fee_capture_status' => 'failed',
                'dispatch_fee_capture_amount_cents' => $feeCents,
            ];
        }

        if ($assignment->company_id && !$paymentIntent->company_id) {
            $paymentIntent->update(['company_id' => $assignment->company_id]);
        }

        $captureCents = min($feeCents, (int) $paymentIntent->amount_cents);

        try {
            $this->payments->capture([
                'payment_intent_id' => $paymentIntent->id,
                'amount_to_capture_cents' => $captureCents,
                'idempotency_key' => 'dispatch_fee_' . $lead->id . '_' . $assignment->id,
            ]);

            return [
                'dispatch_fee_capture_status' => 'captured',
                'dispatch_fee_capture_amount_cents' => $captureCents,
            ];
        } catch (RuntimeException $exception) {
            Log::warning('Dispatch fee capture failed: ' . $exception->getMessage(), [
                'lead_id' => $lead->id,
                'assignment_id' => $assignment->id,
            ]);

            return [
                'dispatch_fee_capture_status' => 'failed',
                'dispatch_fee_capture_amount_cents' => $feeCents,
            ];
        }
    }
}
