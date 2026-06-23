<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class SubscriptionController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    // GET /packages  — public (firma paneli plan sayfası)
    public function packages(): JsonResponse
    {
        $packages = Package::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'description', 'price_monthly', 'price_yearly', 'features', 'max_leads_per_month']);

        return response()->json(['data' => $packages]);
    }

    // POST /subscription/checkout  — Stripe Checkout oturumu oluştur
    public function checkout(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'package_id' => ['required', 'exists:packages,id'],
            'interval'   => ['required', 'in:monthly,yearly'],
        ]);

        $package = Package::findOrFail($validated['package_id']);
        $company = $request->user()->company;

        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $priceId = $validated['interval'] === 'yearly'
            ? $package->stripe_price_id_yearly
            : $package->stripe_price_id_monthly;

        if (!$priceId) {
            return response()->json(['error' => 'Price not configured for this plan'], 422);
        }

        // Stripe customer oluştur (yoksa)
        if (!$company->stripe_customer_id) {
            $customer = $this->stripe->customers->create([
                'name'  => $company->name,
                'email' => $request->user()->email,
                'metadata' => ['company_id' => $company->id],
            ]);
            $company->update(['stripe_customer_id' => $customer->id]);
        }

        $appUrl = config('services.app_url', 'https://app.locknear.com');

        $session = $this->stripe->checkout->sessions->create([
            'customer'              => $company->stripe_customer_id,
            'mode'                  => 'subscription',
            'line_items'            => [['price' => $priceId, 'quantity' => 1]],
            'success_url'           => $appUrl . '/subscription?success=1',
            'cancel_url'            => $appUrl . '/subscription?cancelled=1',
            'subscription_data'     => [
                'trial_period_days' => 14,
                'metadata'          => ['company_id' => $company->id, 'package_id' => $package->id],
            ],
            'allow_promotion_codes' => true,
        ]);

        return response()->json(['checkout_url' => $session->url]);
    }

    // POST /subscription/portal  — Stripe Customer Portal (iptal / fatura)
    public function portal(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if (!$company?->stripe_customer_id) {
            return response()->json(['error' => 'No active subscription'], 404);
        }

        $appUrl = config('services.app_url', 'https://app.locknear.com');

        $session = $this->stripe->billingPortal->sessions->create([
            'customer'   => $company->stripe_customer_id,
            'return_url' => $appUrl . '/subscription',
        ]);

        return response()->json(['portal_url' => $session->url]);
    }

    // GET /subscription  — mevcut plan durumu
    public function current(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        $subscription = $company?->activeSubscription();

        return response()->json([
            'data' => $subscription
                ? $subscription->load('package:id,name,slug,price_monthly,max_leads_per_month')
                : null,
        ]);
    }
}
