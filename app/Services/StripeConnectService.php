<?php

namespace App\Services;

use App\Models\Company;
use App\Models\ProviderPayoutAccount;
use RuntimeException;
use Stripe\StripeClient;
use Throwable;

class StripeConnectService
{
    public function status(Company $company): ProviderPayoutAccount
    {
        $account = $company->payoutAccount ?: ProviderPayoutAccount::create([
            'company_id' => $company->id,
            'processor' => 'stripe',
            'status' => 'not_started',
        ]);

        if ($account->stripe_account_id) {
            $this->sync($account);
        }

        return $account->fresh();
    }

    public function createOrRefreshAccount(Company $company): ProviderPayoutAccount
    {
        $account = $company->payoutAccount ?: ProviderPayoutAccount::create([
            'company_id' => $company->id,
            'processor' => 'stripe',
            'status' => 'not_started',
        ]);

        if (!$account->stripe_account_id) {
            $stripeAccount = $this->withoutAccountsV2Notice(fn () => $this->stripe()->accounts->create(array_filter([
                'type' => 'express',
                'country' => 'US',
                'email' => $company->email,
                'business_type' => 'individual',
                'capabilities' => [
                    'card_payments' => ['requested' => true],
                    'transfers' => ['requested' => true],
                ],
                'business_profile' => array_filter([
                    'name' => $company->name,
                    'url' => $company->website,
                    'product_description' => 'Emergency locksmith services dispatched through LockNear.',
                ]),
                'metadata' => [
                    'company_id' => (string) $company->id,
                    'source' => 'locknear_provider_dashboard',
                ],
            ], fn ($value) => $value !== null)));

            $account->update([
                'stripe_account_id' => $stripeAccount->id,
                'status' => 'created',
            ]);
        }

        return $this->sync($account->fresh());
    }

    public function onboardingLink(Company $company): array
    {
        $account = $this->createOrRefreshAccount($company);

        $link = $this->withoutAccountsV2Notice(fn () => $this->stripe()->accountLinks->create([
            'account' => $account->stripe_account_id,
            'refresh_url' => $this->providerUrl('/billing?stripe_refresh=1'),
            'return_url' => $this->providerUrl('/billing?stripe_return=1'),
            'type' => 'account_onboarding',
        ]));

        return [
            'url' => $link->url,
            'account' => $account->fresh(),
        ];
    }

    public function loginLink(Company $company): ?string
    {
        $account = $this->status($company);
        if (!$account->stripe_account_id || !$account->onboarded_at) {
            return null;
        }

        return $this->withoutAccountsV2Notice(fn () => $this->stripe()->accounts->createLoginLink($account->stripe_account_id)->url);
    }

    public function sync(ProviderPayoutAccount $account): ProviderPayoutAccount
    {
        if (!$account->stripe_account_id) {
            return $account;
        }

        $stripeAccount = $this->withoutAccountsV2Notice(fn () => $this->stripe()->accounts->retrieve($account->stripe_account_id));
        $requirements = $stripeAccount->requirements?->toArray() ?? [];
        $chargesEnabled = (bool) $stripeAccount->charges_enabled;
        $payoutsEnabled = (bool) $stripeAccount->payouts_enabled;
        $detailsSubmitted = (bool) $stripeAccount->details_submitted;

        $account->update([
            'status' => $chargesEnabled && $payoutsEnabled
                ? 'active'
                : ($detailsSubmitted ? 'pending_verification' : 'onboarding_required'),
            'charges_enabled' => $chargesEnabled,
            'payouts_enabled' => $payoutsEnabled,
            'onboarded_at' => $detailsSubmitted ? ($account->onboarded_at ?? now()) : null,
            'requirements' => $requirements,
            'metadata' => [
                ...($account->metadata ?? []),
                'details_submitted' => $detailsSubmitted,
                'default_currency' => $stripeAccount->default_currency ?? null,
                'country' => $stripeAccount->country ?? null,
            ],
        ]);

        return $account->fresh();
    }

    protected function stripe(): StripeClient
    {
        $secret = config('services.stripe.secret');
        if (!$secret || str_contains($secret, 'your_secret')) {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        return new StripeClient($secret);
    }

    protected function providerUrl(string $path): string
    {
        return rtrim(config('services.provider_url'), '/') . $path;
    }

    /**
     * stripe-php 22 emits Stripe-Notice headers as PHP warnings. Laravel converts
     * warnings to exceptions in production, so the non-blocking Accounts v2
     * recommendation can break Connect onboarding.
     */
    protected function withoutAccountsV2Notice(callable $callback): mixed
    {
        set_error_handler(function (int $severity, string $message): bool {
            return $severity === E_USER_WARNING
                && str_contains($message, 'Accounts v2');
        });

        try {
            return $callback();
        } catch (Throwable $e) {
            throw $e;
        } finally {
            restore_error_handler();
        }
    }
}
