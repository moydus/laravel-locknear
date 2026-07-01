<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\LeadAssignment;
use App\Models\PaymentIntent;
use App\Services\PaymentEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReleaseProviderNoShowAuthorizations extends Command
{
    protected $signature = 'locknear:release-provider-no-shows';

    protected $description = 'Release customer card holds when an accepted provider does not arrive in time';

    public function handle(PaymentEngine $payments): int
    {
        if (! config('work_orders.enabled')) {
            return self::SUCCESS;
        }

        $cutoff = now()->subMinutes((int) config('work_orders.provider_no_show_minutes', 60));
        $assignments = LeadAssignment::query()
            ->where(function ($query) use ($cutoff) {
                $query->where(fn ($accepted) => $accepted->where('status', 'accepted')->where('accepted_at', '<=', $cutoff))
                    ->orWhere(fn ($enRoute) => $enRoute->where('status', 'en_route')->where('en_route_at', '<=', $cutoff));
            })
            ->get();

        $released = 0;
        foreach ($assignments as $candidate) {
            $reservation = DB::transaction(function () use ($candidate) {
                $assignment = LeadAssignment::query()->lockForUpdate()->find($candidate->id);
                if (! $assignment || ! in_array($assignment->status, ['accepted', 'en_route'], true)) {
                    return null;
                }

                $previousStatus = $assignment->status;
                $assignment->update(['status' => 'no_show_processing']);

                return [$assignment, $previousStatus];
            });
            if (! $reservation) {
                continue;
            }

            [$assignment, $previousStatus] = $reservation;
            $intent = PaymentIntent::where('lead_id', $assignment->lead_id)
                ->where('status', 'requires_capture')
                ->latest('id')
                ->first();

            try {
                if ($intent) {
                    $payments->cancel([
                        'payment_intent_id' => $intent->id,
                        'cancellation_reason' => 'abandoned',
                        'idempotency_key' => 'provider_no_show_'.$assignment->lead_id,
                    ]);
                }
            } catch (\Throwable $exception) {
                report($exception);
                LeadAssignment::whereKey($assignment->id)
                    ->where('status', 'no_show_processing')
                    ->update(['status' => $previousStatus]);

                continue;
            }

            try {
                DB::transaction(function () use ($assignment, $previousStatus, $intent) {
                    $locked = LeadAssignment::query()->lockForUpdate()->findOrFail($assignment->id);
                    if ($locked->status !== 'no_show_processing') {
                        return;
                    }
                    $lead = $locked->lead()->lockForUpdate()->firstOrFail();
                    $locked->update(['status' => 'provider_no_show']);
                    $lead->update([
                        'status' => 'cancelled',
                        'customer_cancelled_at' => now(),
                        'customer_cancellation_reason' => 'provider_no_show',
                    ]);
                    Booking::where('lead_id', $lead->id)->update(['status' => 'cancelled', 'cancelled_at' => now()]);
                    DB::table('work_order_status_events')->insert([
                        'lead_id' => $lead->id,
                        'company_id' => $locked->company_id,
                        'from_status' => $previousStatus,
                        'to_status' => 'provider_no_show',
                        'actor_type' => 'system',
                        'actor_id' => null,
                        'metadata' => json_encode(['hold_released' => (bool) $intent]),
                        'recorded_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                });
                $released++;
            } catch (\Throwable $exception) {
                report($exception);
                LeadAssignment::whereKey($assignment->id)
                    ->where('status', 'no_show_processing')
                    ->update(['status' => $previousStatus]);
            }
        }

        $this->info("Released {$released} provider no-show authorization(s).");

        return self::SUCCESS;
    }
}
