<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Commission;
use App\Models\Lead;
use App\Models\PaymentIntent as LockNearPaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Payout;
use App\Models\Refund;
use Illuminate\Support\Arr;
use RuntimeException;
use Stripe\StripeClient;

class PaymentEngine
{
    public function __construct(private LeadBillingService $leadBilling) {}

    public function createIntent(array $payload): array
    {
        $amountCents = (int) $payload['amount_cents'];
        $currency = strtolower((string) ($payload['currency'] ?? config('locknear.pricing.default_currency', 'usd')));
        $idempotencyKey = $payload['idempotency_key'] ?? 'pi_' . hash('sha256', json_encode([
            $payload['lead_id'] ?? null,
            $payload['booking_id'] ?? null,
            $amountCents,
            $currency,
            $payload['purpose'] ?? 'service_authorization',
        ]));

        $existing = LockNearPaymentIntent::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            $stripeIntent = $this->stripe()->paymentIntents->retrieve($existing->processor_intent_id);
            $this->syncLocalIntent($existing, $stripeIntent);

            return $this->intentResponse($existing->fresh(), $stripeIntent);
        }

        $stripePayload = [
            'amount' => $amountCents,
            'currency' => $currency,
            'payment_method_types' => ['card'],
            'capture_method' => 'manual',
            'description' => $payload['description'] ?? 'LockNear service authorization',
            'receipt_email' => $payload['receipt_email'] ?? null,
            'metadata' => array_filter([
                'lead_id' => $payload['lead_id'] ?? null,
                'booking_id' => $payload['booking_id'] ?? null,
                'company_id' => $payload['company_id'] ?? null,
                'purpose' => $payload['purpose'] ?? 'service_authorization',
                'idempotency_key' => $idempotencyKey,
            ], fn ($value) => $value !== null && $value !== ''),
        ];

        $stripeIntent = $this->stripe()->paymentIntents->create(
            array_filter($stripePayload, fn ($value) => $value !== null),
            ['idempotency_key' => $idempotencyKey],
        );

        $local = LockNearPaymentIntent::create([
            'booking_id' => $payload['booking_id'] ?? null,
            'lead_id' => $payload['lead_id'] ?? null,
            'company_id' => $payload['company_id'] ?? null,
            'idempotency_key' => $idempotencyKey,
            'payer_type' => $payload['payer_type'] ?? 'customer',
            'purpose' => $payload['purpose'] ?? 'service_authorization',
            'status' => $stripeIntent->status,
            'amount' => $this->centsToAmount($amountCents),
            'amount_cents' => $amountCents,
            'captured_amount' => 0,
            'captured_amount_cents' => 0,
            'currency' => $currency,
            'processor' => 'stripe',
            'processor_intent_id' => $stripeIntent->id,
            'processor_charge_id' => $this->latestChargeId($stripeIntent),
            'metadata' => $payload['metadata'] ?? [],
        ]);

        return $this->intentResponse($local, $stripeIntent);
    }

    public function authorize(array $payload): array
    {
        $local = $this->resolveLocalIntent($payload);

        if (!empty($payload['payment_method'])) {
            $stripeIntent = $this->stripe()->paymentIntents->confirm(
                $local->processor_intent_id,
                ['payment_method' => $payload['payment_method']],
                ['idempotency_key' => $payload['idempotency_key'] ?? 'confirm_' . $local->id . '_' . $payload['payment_method']],
            );
        } else {
            $stripeIntent = $this->stripe()->paymentIntents->retrieve($local->processor_intent_id);
        }

        $this->syncLocalIntent($local, $stripeIntent);

        return $this->intentResponse($local->fresh(), $stripeIntent);
    }

    public function capture(array $payload): array
    {
        $local = $this->resolveLocalIntent($payload);
        $capturePayload = [];

        if (!empty($payload['amount_to_capture_cents'])) {
            $capturePayload['amount_to_capture'] = (int) $payload['amount_to_capture_cents'];
        }

        $stripeIntent = $this->stripe()->paymentIntents->capture(
            $local->processor_intent_id,
            $capturePayload,
            ['idempotency_key' => $payload['idempotency_key'] ?? 'capture_' . $local->id . '_' . ($capturePayload['amount_to_capture'] ?? 'full')],
        );

        $this->syncLocalIntent($local, $stripeIntent);
        $local = $local->fresh();
        $capturedCents = (int) ($stripeIntent->amount_received ?? $local->captured_amount_cents ?? 0);

        $transaction = PaymentTransaction::updateOrCreate(
            [
                'payment_intent_id' => $local->id,
                'type' => 'capture',
                'processor_id' => $stripeIntent->id,
            ],
            [
                'booking_id' => $local->booking_id,
                'lead_id' => $local->lead_id,
                'company_id' => $local->company_id,
                'status' => $stripeIntent->status === 'succeeded' ? 'succeeded' : $stripeIntent->status,
                'gross_amount' => $this->centsToAmount($capturedCents),
                'gross_amount_cents' => $capturedCents,
                'fee_amount' => 0,
                'fee_amount_cents' => 0,
                'net_amount' => $this->centsToAmount($capturedCents),
                'net_amount_cents' => $capturedCents,
                'currency' => $local->currency,
                'processor' => 'stripe',
                'processed_at' => now(),
                'metadata' => ['stripe_status' => $stripeIntent->status],
            ],
        );

        if ($local->company_id && $capturedCents > 0) {
            $commission = $this->recordCommission($local, $transaction, $capturedCents);
            $payout = $this->createProviderTransfer($local, $commission, $payload);
        }

        return array_merge($this->intentResponse($local, $stripeIntent), [
            'transaction_id' => $transaction->id,
            'payout_id' => $payout->id ?? null,
            'stripe_transfer_id' => $payout->stripe_transfer_id ?? null,
        ]);
    }

    public function refund(array $payload): array
    {
        $local = $this->resolveLocalIntent($payload);
        $refundPayload = ['payment_intent' => $local->processor_intent_id];

        if (!empty($payload['amount_cents'])) {
            $refundPayload['amount'] = (int) $payload['amount_cents'];
        }

        if (!empty($payload['reason'])) {
            $refundPayload['reason'] = $payload['reason'];
        }

        $stripeRefund = $this->stripe()->refunds->create(
            $refundPayload,
            ['idempotency_key' => $payload['idempotency_key'] ?? 'refund_' . $local->id . '_' . ($refundPayload['amount'] ?? 'full')],
        );

        $refund = Refund::create([
            'booking_id' => $local->booking_id,
            'payment_intent_id' => $local->id,
            'amount' => $this->centsToAmount((int) $stripeRefund->amount),
            'currency' => strtolower($stripeRefund->currency ?? $local->currency),
            'reason' => $payload['reason'] ?? null,
            'status' => $stripeRefund->status,
            'processor_refund_id' => $stripeRefund->id,
            'processed_at' => now(),
            'metadata' => ['stripe_status' => $stripeRefund->status],
        ]);

        return [
            'status' => $stripeRefund->status,
            'refund_id' => $refund->id,
            'processor_refund_id' => $stripeRefund->id,
        ];
    }

    public function cancel(array $payload): array
    {
        $local = $this->resolveLocalIntent($payload);
        $stripeIntent = $this->stripe()->paymentIntents->cancel(
            $local->processor_intent_id,
            Arr::only($payload, ['cancellation_reason']),
            ['idempotency_key' => $payload['idempotency_key'] ?? 'cancel_' . $local->id],
        );

        $this->syncLocalIntent($local, $stripeIntent);

        return $this->intentResponse($local->fresh(), $stripeIntent);
    }

    public function payout(array $payload): array
    {
        $intent = $this->resolveLocalIntent($payload);
        $transaction = PaymentTransaction::where('payment_intent_id', $intent->id)
            ->where('type', 'capture')
            ->latest('id')
            ->first();

        if (!$transaction) {
            throw new RuntimeException('Captured payment transaction not found.');
        }

        $commission = Commission::where('payment_transaction_id', $transaction->id)->first();
        if (!$commission) {
            throw new RuntimeException('Commission record not found.');
        }

        $payout = $this->createProviderTransfer($intent, $commission, $payload);

        return [
            'status' => $payout->status,
            'payout_id' => $payout->id,
            'stripe_transfer_id' => $payout->stripe_transfer_id,
            'net_amount_cents' => $payout->net_amount_cents,
            'currency' => $payout->currency,
        ];
    }

    public function chargeProviderLeadFee(Company $company, Lead $lead, float $amount, ?string $existingChargeId = null): ?string
    {
        return $this->leadBilling->chargeForAccept($company, $lead, $amount, $existingChargeId);
    }

    protected function stripe(): StripeClient
    {
        $secret = config('services.stripe.secret');
        if (!$secret || str_contains($secret, 'your_secret')) {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        return new StripeClient($secret);
    }

    protected function resolveLocalIntent(array $payload): LockNearPaymentIntent
    {
        if (!empty($payload['payment_intent_id'])) {
            return LockNearPaymentIntent::findOrFail($payload['payment_intent_id']);
        }

        if (!empty($payload['processor_intent_id'])) {
            return LockNearPaymentIntent::where('processor_intent_id', $payload['processor_intent_id'])->firstOrFail();
        }

        throw new RuntimeException('payment_intent_id or processor_intent_id is required.');
    }

    protected function syncLocalIntent(LockNearPaymentIntent $local, object $stripeIntent): void
    {
        $capturedCents = (int) ($stripeIntent->amount_received ?? 0);

        $local->update([
            'status' => $stripeIntent->status,
            'processor_charge_id' => $this->latestChargeId($stripeIntent),
            'captured_amount' => $this->centsToAmount($capturedCents),
            'captured_amount_cents' => $capturedCents,
            'authorized_at' => $stripeIntent->status === 'requires_capture'
                ? ($local->authorized_at ?? now())
                : $local->authorized_at,
            'captured_at' => $stripeIntent->status === 'succeeded'
                ? ($local->captured_at ?? now())
                : $local->captured_at,
            'cancelled_at' => $stripeIntent->status === 'canceled'
                ? ($local->cancelled_at ?? now())
                : $local->cancelled_at,
        ]);
    }

    protected function intentResponse(LockNearPaymentIntent $local, object $stripeIntent): array
    {
        return [
            'id' => $local->id,
            'status' => $stripeIntent->status,
            'processor_intent_id' => $stripeIntent->id,
            'client_secret' => $stripeIntent->client_secret ?? null,
            'amount_cents' => (int) $local->amount_cents,
            'captured_amount_cents' => (int) $local->captured_amount_cents,
            'currency' => $local->currency,
            'requires_capture' => $stripeIntent->status === 'requires_capture',
        ];
    }

    protected function latestChargeId(object $stripeIntent): ?string
    {
        if (is_string($stripeIntent->latest_charge ?? null)) {
            return $stripeIntent->latest_charge;
        }

        return $stripeIntent->latest_charge?->id ?? null;
    }

    protected function centsToAmount(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    protected function recordCommission(LockNearPaymentIntent $intent, PaymentTransaction $transaction, int $capturedCents): Commission
    {
        $commissionContext = $this->resolveCommissionContext($intent->company_id);
        $pricing = app(PricingEngine::class)->calculate($capturedCents, $commissionContext['rate']);

        return Commission::updateOrCreate(
            [
                'booking_id' => $intent->booking_id,
                'payment_transaction_id' => $transaction->id,
                'company_id' => $intent->company_id,
            ],
            [
                'rate' => $pricing['commission_rate'],
                'service_total' => $this->centsToAmount($pricing['service_total_cents']),
                'service_total_cents' => $pricing['service_total_cents'],
                'platform_fee' => $this->centsToAmount($pricing['platform_fee_cents']),
                'platform_fee_cents' => $pricing['platform_fee_cents'],
                'provider_amount' => $this->centsToAmount($pricing['provider_amount_cents']),
                'provider_amount_cents' => $pricing['provider_amount_cents'],
                'tax_amount' => $this->centsToAmount($pricing['tax_amount_cents']),
                'tax_amount_cents' => $pricing['tax_amount_cents'],
                'tip_amount' => $this->centsToAmount($pricing['tip_amount_cents']),
                'tip_amount_cents' => $pricing['tip_amount_cents'],
                'discount_amount' => $this->centsToAmount($pricing['discount_amount_cents']),
                'discount_amount_cents' => $pricing['discount_amount_cents'],
                'currency' => $intent->currency,
                'status' => 'collected',
                'collected_at' => now(),
                'metadata' => [
                    'source' => 'stripe_capture',
                    'plan' => $commissionContext['plan'],
                    'subscription_id' => $commissionContext['subscription_id'],
                    'subscription_interval' => $commissionContext['interval'],
                ],
            ],
        );
    }

    protected function resolveCommissionContext(?int $companyId): array
    {
        $fallbackRate = (float) config('locknear.pricing.default_commission_rate', 0.20);
        $context = [
            'rate' => $fallbackRate,
            'plan' => 'free',
            'subscription_id' => null,
            'interval' => null,
        ];

        if (!$companyId) {
            return $context;
        }

        $company = Company::find($companyId);
        $subscription = $company?->activeSubscription()?->load('package');
        $package = $subscription?->package;

        if (!$subscription || !$package) {
            return $context;
        }

        $interval = $subscription->interval === 'yearly' ? 'yearly' : 'monthly';
        $rate = $interval === 'yearly'
            ? $package->commission_yearly
            : $package->commission_monthly;

        return [
            'rate' => $rate !== null ? (float) $rate : $fallbackRate,
            'plan' => $package->slug,
            'subscription_id' => $subscription->id,
            'interval' => $interval,
        ];
    }

    protected function createProviderTransfer(LockNearPaymentIntent $intent, Commission $commission, array $payload = []): ?Payout
    {
        if (!$intent->company_id) {
            return null;
        }

        $company = Company::with('payoutAccount')->find($intent->company_id);
        $account = $company?->payoutAccount;

        $payout = Payout::query()
            ->where('company_id', $intent->company_id)
            ->where('metadata->payment_intent_id', $intent->id)
            ->first();

        if (!$payout) {
            $payout = Payout::create([
                'company_id' => $intent->company_id,
                'status' => 'pending',
                'gross_amount' => $commission->service_total,
                'gross_amount_cents' => $commission->service_total_cents,
                'fee_amount' => $commission->platform_fee,
                'fee_amount_cents' => $commission->platform_fee_cents,
                'net_amount' => $commission->provider_amount,
                'net_amount_cents' => $commission->provider_amount_cents,
                'currency' => $intent->currency,
                'processor' => 'stripe',
                'stripe_account_id' => $account?->stripe_account_id,
                'scheduled_at' => now(),
                'metadata' => [
                    'payment_intent_id' => $intent->id,
                    'processor_intent_id' => $intent->processor_intent_id,
                    'commission_id' => $commission->id,
                    'mode' => 'separate_charges_and_transfers',
                ],
            ]);
        }

        if ($payout->stripe_transfer_id) {
            return $payout;
        }

        if (!$account?->stripe_account_id || !$account->payouts_enabled) {
            $payout->update([
                'status' => 'pending',
                'stripe_account_id' => $account?->stripe_account_id,
                'metadata' => [
                    ...($payout->metadata ?? []),
                    'hold_reason' => $account?->stripe_account_id ? 'payouts_not_enabled' : 'stripe_account_missing',
                ],
            ]);

            return $payout->fresh();
        }

        if (!$intent->processor_charge_id) {
            $payout->update([
                'status' => 'pending',
                'metadata' => [
                    ...($payout->metadata ?? []),
                    'hold_reason' => 'stripe_charge_missing',
                ],
            ]);

            return $payout->fresh();
        }

        $transfer = $this->stripe()->transfers->create(
            [
                'amount' => (int) $commission->provider_amount_cents,
                'currency' => $intent->currency,
                'destination' => $account->stripe_account_id,
                'source_transaction' => $intent->processor_charge_id,
                'metadata' => [
                    'payment_intent_id' => (string) $intent->id,
                    'processor_intent_id' => $intent->processor_intent_id,
                    'commission_id' => (string) $commission->id,
                    'company_id' => (string) $intent->company_id,
                ],
            ],
            ['idempotency_key' => $payload['transfer_idempotency_key'] ?? 'transfer_' . $intent->id . '_' . $commission->id],
        );

        $payout->update([
            'status' => 'transferred',
            'stripe_account_id' => $account->stripe_account_id,
            'stripe_transfer_id' => $transfer->id,
            'paid_at' => now(),
            'metadata' => [
                ...($payout->metadata ?? []),
                'stripe_transfer_destination' => $transfer->destination,
            ],
        ]);

        return $payout->fresh();
    }
}
