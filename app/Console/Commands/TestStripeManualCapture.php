<?php

namespace App\Console\Commands;

use App\Services\PaymentEngine;
use Illuminate\Console\Command;
use Throwable;

class TestStripeManualCapture extends Command
{
    protected $signature = 'locknear:test-stripe-payment
        {--amount=12900 : Amount in cents}
        {--currency=usd : Three-letter currency code}
        {--capture : Capture after authorization}
        {--payment-method=pm_card_visa : Stripe test payment method id}';

    protected $description = 'Create and confirm a Stripe manual-capture PaymentIntent for smoke testing';

    public function handle(PaymentEngine $payments): int
    {
        $amountCents = (int) $this->option('amount');
        $currency = strtolower((string) $this->option('currency'));
        $paymentMethod = (string) $this->option('payment-method');

        try {
            $created = $payments->createIntent([
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'purpose' => 'stripe_smoke_test',
                'description' => 'LockNear Stripe manual capture smoke test',
                'idempotency_key' => 'smoke_' . now()->format('YmdHis') . '_' . $amountCents,
                'metadata' => ['source' => 'artisan_smoke_test'],
            ]);

            $this->info("Created local #{$created['id']} / Stripe {$created['processor_intent_id']} ({$created['status']})");

            $authorized = $payments->authorize([
                'payment_intent_id' => $created['id'],
                'payment_method' => $paymentMethod,
            ]);

            $this->info("Authorized: {$authorized['status']} requires_capture=" . (($authorized['requires_capture'] ?? false) ? 'yes' : 'no'));

            if ($this->option('capture')) {
                $captured = $payments->capture([
                    'payment_intent_id' => $created['id'],
                ]);

                $this->info("Captured: {$captured['status']} amount={$captured['captured_amount_cents']} {$captured['currency']}");
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
