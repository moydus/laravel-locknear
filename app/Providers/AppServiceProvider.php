<?php

namespace App\Providers;

use App\Events\LeadCompleted;
use App\Listeners\SendPostJobSurvey;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(LeadCompleted::class, SendPostJobSurvey::class);

        $this->registerCloudflareMailTransport();

        RateLimiter::for('customer-messages', function (Request $request) {
            $token = $request->route('token') ?? 'unknown';
            return Limit::perMinute(8)->by($token . '|' . $request->ip())
                ->response(fn () => response()->json(['error' => 'Too many messages. Please wait before sending again.'], 429));
        });

        // Reset password linkini app.locknear.com'a yönlendir
        ResetPassword::createUrlUsing(function ($user, string $token) {
            $appUrl = rtrim((string) config('services.provider_url', 'https://app.locknear.com'), '/');
            return $appUrl . '/auth/reset-password?token=' . $token . '&email=' . urlencode($user->email);
        });
    }

    private function registerCloudflareMailTransport(): void
    {
        $this->app->make('mail.manager')->extend('cloudflare', function () {
            return new \App\Mail\Transport\CloudflareTransport(
                config('services.cloudflare.account_id'),
                config('services.cloudflare.api_token'),
            );
        });
    }
}
