<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $payload = $request->getContent();
        $sig     = $request->header('Stripe-Signature');
        $secret  = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sig, $secret);
        } catch (SignatureVerificationException) {
            return response('Invalid signature', 400);
        }

        match ($event->type) {
            'customer.subscription.created',
            'customer.subscription.updated'  => $this->upsertSubscription($event->data->object),
            'customer.subscription.deleted'  => $this->cancelSubscription($event->data->object),
            default                          => null,
        };

        return response('OK', 200);
    }

    private function upsertSubscription(object $sub): void
    {
        $companyId = $sub->metadata['company_id'] ?? null;
        $packageId = $sub->metadata['package_id'] ?? null;

        if (!$companyId) return;

        $company = Company::find($companyId);
        if (!$company) return;

        // stripe_price_id ile package eşleştir (yoksa metadata'dan al)
        $package = $packageId
            ? Package::find($packageId)
            : Package::where('stripe_price_id_monthly', $sub->items->data[0]->price->id)
                     ->orWhere('stripe_price_id_yearly', $sub->items->data[0]->price->id)
                     ->first();

        Subscription::updateOrCreate(
            ['stripe_subscription_id' => $sub->id],
            [
                'company_id'           => $companyId,
                'package_id'           => $package?->id,
                'status'               => $sub->status,
                'trial_ends_at'        => $sub->trial_end ? now()->setTimestamp($sub->trial_end) : null,
                'current_period_start' => now()->setTimestamp($sub->current_period_start),
                'current_period_end'   => now()->setTimestamp($sub->current_period_end),
                'cancelled_at'         => $sub->canceled_at ? now()->setTimestamp($sub->canceled_at) : null,
            ]
        );

        if (in_array($sub->status, ['active', 'trialing'])) {
            $company->update(['is_active' => true]);
        } elseif (in_array($sub->status, ['past_due', 'unpaid', 'incomplete_expired'])) {
            $company->update(['is_active' => false]);
        }
    }

    private function cancelSubscription(object $sub): void
    {
        Subscription::where('stripe_subscription_id', $sub->id)
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $companyId = $sub->metadata['company_id'] ?? null;
        if ($companyId) {
            Company::find($companyId)?->update(['is_active' => false]);
        }
    }
}
