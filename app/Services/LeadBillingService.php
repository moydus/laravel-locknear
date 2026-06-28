<?php

namespace App\Services;

use App\Exceptions\LeadBillingException;
use App\Mail\ProviderLeadChargeMail;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadAssignment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\StripeClient;

class LeadBillingService
{
    private ?StripeClient $stripe = null;

    public function isEnabled(): bool
    {
        return (bool) config('services.stripe.secret')
            && config('services.stripe.lead_billing_enabled', false);
    }

    public function chargeForAccept(Company $company, Lead $lead, float $amount, ?string $existingChargeId = null): ?string
    {
        if ($existingChargeId) {
            return $existingChargeId;
        }

        if (!$this->isEnabled()) {
            Log::info("Lead billing skipped for company {$company->id}, lead {$lead->id}");

            return null;
        }

        $this->assertWithinMonthlyQuota($company);

        if (!$company->stripe_customer_id) {
            throw new LeadBillingException(
                'Add a payment method in Subscription settings before accepting leads.',
                'missing_payment_method',
            );
        }

        $paymentMethodId = $this->resolveDefaultPaymentMethod($company->stripe_customer_id);
        if (!$paymentMethodId) {
            throw new LeadBillingException(
                'Add a card on file in Subscription settings before accepting leads.',
                'missing_payment_method',
            );
        }

        $service = str($lead->service_type)->replace('-', ' ')->title();
        $amountCents = (int) round($amount * 100);

        try {
            $intent = $this->stripe()->paymentIntents->create([
                'amount' => $amountCents,
                'currency' => 'usd',
                'customer' => $company->stripe_customer_id,
                'payment_method' => $paymentMethodId,
                'off_session' => true,
                'confirm' => true,
                'description' => "LockNear lead #{$lead->id} — {$service}",
                'metadata' => [
                    'lead_id' => (string) $lead->id,
                    'company_id' => (string) $company->id,
                    'service_type' => $lead->service_type,
                ],
            ]);
        } catch (CardException $e) {
            throw new LeadBillingException(
                'Your card was declined. Update your payment method in Subscription settings.',
                'card_declined',
            );
        } catch (ApiErrorException $e) {
            Log::warning("Stripe lead charge failed for company {$company->id}: " . $e->getMessage());
            throw new LeadBillingException(
                'Unable to process the lead fee right now. Please try again.',
                'stripe_error',
            );
        }

        if ($intent->status !== 'succeeded') {
            throw new LeadBillingException(
                'Payment requires additional action. Open Subscription settings to verify your card.',
                'payment_incomplete',
            );
        }

        return is_string($intent->latest_charge) ? $intent->latest_charge : $intent->id;
    }

    public function sendProviderReceipt(Company $company, Lead $lead, float $amount, ?string $chargeId): void
    {
        $email = $company->email;
        if (!$email) {
            $company->loadMissing('user');
            $email = $company->user?->email;
        }
        if (!$email) {
            return;
        }

        try {
            Mail::to($email)->send(new ProviderLeadChargeMail($company, $lead, $amount, $chargeId));
        } catch (\Exception $e) {
            Log::warning("Provider lead receipt email failed for company {$company->id}: " . $e->getMessage());
        }
    }

    protected function assertWithinMonthlyQuota(Company $company): void
    {
        $subscription = $company->activeSubscription();
        $limit = $subscription?->package?->max_leads_per_month;

        if (!$limit) {
            return;
        }

        $used = LeadAssignment::query()
            ->where('company_id', $company->id)
            ->whereNotNull('stripe_charge_id')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        if ($used >= $limit) {
            throw new LeadBillingException(
                "Monthly lead limit ({$limit}) reached. Upgrade your plan to accept more jobs.",
                'lead_quota_exceeded',
            );
        }
    }

    protected function resolveDefaultPaymentMethod(string $customerId): ?string
    {
        $customer = $this->stripe()->customers->retrieve($customerId);

        if (!empty($customer->invoice_settings->default_payment_method)) {
            return (string) $customer->invoice_settings->default_payment_method;
        }

        if (!empty($customer->default_source)) {
            return (string) $customer->default_source;
        }

        $methods = $this->stripe()->paymentMethods->all([
            'customer' => $customerId,
            'type' => 'card',
            'limit' => 1,
        ]);

        return $methods->data[0]->id ?? null;
    }

    protected function stripe(): StripeClient
    {
        if ($this->stripe === null) {
            $this->stripe = new StripeClient(config('services.stripe.secret'));
        }

        return $this->stripe;
    }
}
