<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class CustomerGoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse|Response
    {
        $request->validate([
            'redirect_uri' => ['required', 'url'],
        ]);

        $redirectUri = $request->query('redirect_uri');

        if (!$this->isAllowedFrontendRedirect($redirectUri)) {
            abort(400, 'Invalid redirect_uri.');
        }

        $state = Str::random(48);
        Cache::put($this->stateKey($state), $redirectUri, now()->addMinutes(10));

        return Socialite::driver('google')
            ->stateless()
            ->redirectUrl($this->googleCallbackUrl())
            ->with(['state' => $state])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state');
        $frontendRedirect = $state ? Cache::pull($this->stateKey($state)) : null;

        if (!$frontendRedirect || !$this->isAllowedFrontendRedirect($frontendRedirect)) {
            return redirect($this->frontendErrorUrl('google_auth_failed'));
        }

        try {
            $googleUser = Socialite::driver('google')
                ->stateless()
                ->redirectUrl($this->googleCallbackUrl())
                ->user();
        } catch (\Throwable) {
            return redirect($this->appendQuery($frontendRedirect, [
                'error' => 'google_auth_failed',
            ]));
        }

        $email = $googleUser->getEmail();
        if (!$email) {
            return redirect($this->appendQuery($frontendRedirect, [
                'error' => 'google_email_missing',
            ]));
        }

        $existing = User::where('email', $email)->first();
        if ($existing && $existing->isBusiness()) {
            return redirect($this->appendQuery($frontendRedirect, [
                'error' => 'This email is registered as a locksmith account.',
            ]));
        }

        if ($existing && $existing->isCustomer()) {
            $user = $existing;
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        } else {
            $user = User::create([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'role' => User::ROLE_CUSTOMER,
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
            ]);
        }

        $token = $user->createToken('locknear-customer')->plainTextToken;

        return redirect($this->appendQuery($frontendRedirect, [
            'token' => $token,
        ]));
    }

    protected function isAllowedFrontendRedirect(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return false;
        }

        $allowed = array_filter(array_map('trim', explode(',', env('CUSTOMER_AUTH_REDIRECT_HOSTS', 'locknear.com,localhost,127.0.0.1'))));

        foreach ($allowed as $pattern) {
            if ($host === $pattern || str_ends_with($host, '.'.$pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function googleCallbackUrl(): string
    {
        return rtrim(config('app.url'), '/').'/api/customer/auth/google/callback';
    }

    protected function stateKey(string $state): string
    {
        return 'customer_google_oauth:'.$state;
    }

    /**
     * @param  array<string, string>  $params
     */
    protected function appendQuery(string $url, array $params): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($params);
    }

    protected function frontendErrorUrl(string $error): string
    {
        $base = rtrim(env('FRONTEND_URL', 'https://locknear.com'), '/');

        return $base.'/login?login=1&error='.urlencode($error);
    }
}
