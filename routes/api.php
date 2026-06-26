<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessGoogleAuthController;
use App\Http\Controllers\Api\ClaimController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\CustomerGoogleAuthController;
use App\Http\Controllers\Api\DispatchController;
use App\Http\Controllers\Api\EmailVerificationController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadMessageController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\PasswordResetController;
use App\Http\Controllers\Api\PaymentIntentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Business auth (app.locknear.com — locksmiths)
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [PasswordResetController::class, 'sendLink']);
    Route::post('/auth/reset-password', [PasswordResetController::class, 'reset']);
    Route::get('/auth/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::get('/auth/google/redirect', [BusinessGoogleAuthController::class, 'redirect']);
    Route::get('/auth/google/callback', [BusinessGoogleAuthController::class, 'callback']);
});
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/verify-email/resend', [EmailVerificationController::class, 'resend']);
    Route::post('/onboarding', [OnboardingController::class, 'store']);
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete']);
});

// Customer auth (locknear.com — end users)
Route::post('/customer/register', [CustomerAuthController::class, 'register']);
Route::post('/customer/login', [CustomerAuthController::class, 'login']);
Route::get('/customer/auth/google/redirect', [CustomerGoogleAuthController::class, 'redirect']);
Route::get('/customer/auth/google/callback', [CustomerGoogleAuthController::class, 'callback']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/customer/me', [CustomerAuthController::class, 'me']);
    Route::post('/customer/logout', [CustomerAuthController::class, 'logout']);
    Route::get('/customer/leads', [CustomerAuthController::class, 'leads']);
});

// Public API routes (Astro site uses these — X-API-Key required)
Route::middleware(['throttle:5,1', 'api.key'])->group(function () {
    Route::post('/leads', [LeadController::class, 'store']);
    Route::post('/payment-intents', [PaymentIntentController::class, 'store']);
    Route::post('/contact', [ContactController::class, 'store']);
});

// Dispatch token routes (linked in SMS, no auth required)
Route::get('/dispatch/accept/{token}', [DispatchController::class, 'accept']);
Route::get('/dispatch/reject/{token}', [DispatchController::class, 'reject']);
Route::get('/dispatch/review/{token}', [DispatchController::class, 'review']);
Route::post('/dispatch/review/{token}', [DispatchController::class, 'submitReview']);

// Customer tracking (public — customer_token from lead)
Route::get('/track/{token}', function (string $token) {
    $lead = \App\Models\Lead::where('customer_token', $token)
        ->with('assignments.company')
        ->firstOrFail();

    return response()->json(\App\Support\TrackPayload::forLead($lead));
});

Route::get('/track/{token}/stream', function (string $token) {
    $headers = [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache, no-transform',
        'Connection' => 'keep-alive',
        'X-Accel-Buffering' => 'no',
    ];

    return response()->stream(function () use ($token) {
        @set_time_limit(0);

        $sendSnapshot = function () use ($token) {
            $lead = \App\Models\Lead::where('customer_token', $token)
                ->with('assignments.company')
                ->first();

            if (!$lead) {
                echo "event: error\n";
                echo 'data: ' . json_encode(['error' => 'not_found']) . "\n\n";
                @ob_flush();
                @flush();
                return false;
            }

            $payload = \App\Support\TrackPayload::forLead($lead);

            echo "event: update\n";
            echo 'data: ' . json_encode($payload) . "\n\n";
            @ob_flush();
            @flush();
            return true;
        };

        // Initial payload
        if (!$sendSnapshot()) {
            return;
        }

        // Keep stream alive up to ~2 minutes; browser reconnects automatically.
        for ($i = 0; $i < 40; $i++) {
            if (connection_aborted()) {
                break;
            }
            usleep(3000000); // 3s
            if (!$sendSnapshot()) {
                break;
            }
        }
    }, 200, $headers);
});

Route::get('/track/{token}/messages', [LeadMessageController::class, 'indexByTrackToken']);
Route::post('/track/{token}/messages', [LeadMessageController::class, 'storeByTrackToken'])
    ->middleware('throttle:customer-messages');

Route::get('/companies', [CompanyController::class, 'index']);
Route::get('/companies/{company}', [CompanyController::class, 'show']);
Route::get('/companies/{company}/reviews', [ReviewController::class, 'index']);
Route::get('/companies/{company}/reviews/summary', [ReviewController::class, 'summary']);

// Stripe webhook — CSRF dışında tutulmalı, raw body gerektirir
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);

// Packages — public
Route::get('/packages', [SubscriptionController::class, 'packages']);

// Claim — token ile firma sahiplenme
Route::get('/claim/{token}', [ClaimController::class, 'show']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/claim/{token}', [ClaimController::class, 'claim']);
    Route::post('/claim/manual', [ClaimController::class, 'manual']);
});

// Authenticated routes (firma paneli)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'me']);

    // Company management (firma kendi profilini yönetir)
    Route::get('/company/me', [CompanyController::class, 'me']);
    Route::put('/company/me', [CompanyController::class, 'update']);
    Route::post('/company/me/heartbeat', [CompanyController::class, 'heartbeat']);
    Route::post('/company/me/logo', [CompanyController::class, 'uploadLogo']);
    Route::delete('/company/me', [CompanyController::class, 'destroy']);

    // Services
    Route::get('/services', [ServiceController::class, 'index']);
    Route::put('/services', [ServiceController::class, 'sync']);

    // Subscription
    Route::get('/subscription', [SubscriptionController::class, 'current']);
    Route::post('/subscription/checkout', [SubscriptionController::class, 'checkout']);
    Route::post('/subscription/portal', [SubscriptionController::class, 'portal']);
    Route::get('/billing/lead-charges', [BillingController::class, 'leadCharges']);

    // Payment operations for authorized dispatch
    Route::post('/payment-intents/{paymentIntent}/authorize', [PaymentIntentController::class, 'authorize']);
    Route::post('/payment-intents/{paymentIntent}/capture', [PaymentIntentController::class, 'capture']);
    Route::post('/payment-intents/{paymentIntent}/cancel', [PaymentIntentController::class, 'cancel']);
    Route::post('/payment-intents/{paymentIntent}/refund', [PaymentIntentController::class, 'refund']);

    // Lead management for companies
    Route::get('/leads/stats', [LeadController::class, 'stats']);
    Route::get('/leads', [LeadController::class, 'index']);
    Route::get('/leads/{lead}', [LeadController::class, 'show']);
    Route::get('/leads/{lead}/stream', [LeadController::class, 'stream']);
    Route::get('/leads/{lead}/messages', [LeadMessageController::class, 'indexForProvider']);
    Route::post('/leads/{lead}/messages', [LeadMessageController::class, 'storeForProvider']);
    Route::post('/leads/{lead}/accept', [LeadController::class, 'accept']);
    Route::post('/leads/{lead}/reject', [LeadController::class, 'reject']);
    Route::post('/leads/{lead}/en-route', [LeadController::class, 'markEnRoute']);
    Route::post('/leads/{lead}/arrived', [LeadController::class, 'markArrived']);
    Route::post('/leads/{lead}/complete', [LeadController::class, 'complete']);
    Route::post('/leads/{lead}/location', [LeadController::class, 'updateLocation']);
});
