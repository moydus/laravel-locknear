<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class BusinessGoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse|Response
    {
        $request->validate([
            'redirect_uri' => ['required', 'url'],
            'intent' => ['nullable', 'in:login,register'],
        ]);

        $redirectUri = $request->query('redirect_uri');
        $intent = $request->query('intent', 'login');

        if (!$this->isAllowedFrontendRedirect($redirectUri)) {
            abort(400, 'Invalid redirect_uri.');
        }

        $state = Str::random(48);
        Cache::put($this->stateKey($state), [
            'redirect_uri' => $redirectUri,
            'intent' => $intent,
        ], now()->addMinutes(10));

        return Socialite::driver('google')
            ->stateless()
            ->redirectUrl($this->googleCallbackUrl())
            ->with(['state' => $state])
            ->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        $state = $request->query('state');
        $cached = $state ? Cache::pull($this->stateKey($state)) : null;
        $frontendRedirect = is_array($cached) ? ($cached['redirect_uri'] ?? null) : null;
        $intent = is_array($cached) ? ($cached['intent'] ?? 'login') : 'login';

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
        if ($existing && $existing->isCustomer()) {
            return redirect($this->appendQuery($frontendRedirect, [
                'error' => 'customer_account',
            ]));
        }

        if ($existing && $existing->isBusiness()) {
            $user = $existing;
            if (!$user->google_id) {
                $user->update(['google_id' => $googleUser->getId()]);
            }
        } elseif ($intent === 'register') {
            $user = User::create([
                'name' => $googleUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'role' => User::ROLE_BUSINESS,
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
            ]);

            Company::create([
                'user_id' => $user->id,
                'name' => $user->name,
                'slug' => str($user->name)->slug()->append('-'.$user->id),
            ]);
        } else {
            return redirect($this->appendQuery($frontendRedirect, [
                'error' => 'account_not_found',
            ]));
        }

        $token = $user->createToken('app-locknear')->plainTextToken;

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

        $allowed = array_filter(array_map('trim', explode(',', env(
            'BUSINESS_AUTH_REDIRECT_HOSTS',
            'app.locknear.com,localhost,127.0.0.1'
        ))));

        foreach ($allowed as $pattern) {
            if ($host === $pattern || str_ends_with($host, '.'.$pattern)) {
                return true;
            }
        }

        return false;
    }

    protected function googleCallbackUrl(): string
    {
        return rtrim(config('app.url'), '/').'/api/auth/google/callback';
    }

    protected function stateKey(string $state): string
    {
        return 'business_google_oauth:'.$state;
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
        $base = rtrim(env('BUSINESS_APP_URL', 'http://localhost:3000'), '/');

        return $base.'/auth/login?error='.urlencode($error);
    }
}
