<?php

namespace App\Console\Commands;

use App\Models\Package;
use Illuminate\Console\Command;

class SyncPackagePrices extends Command
{
    protected $signature = 'locknear:sync-package-prices';

    protected $description = 'Sync Stripe price IDs from STRIPE_PRICE_* env vars into packages';

    public function handle(): int
    {
        $mapping = [
            'professional' => [
                'monthly' => env('STRIPE_PRICE_PROFESSIONAL_MONTHLY'),
                'yearly' => env('STRIPE_PRICE_PROFESSIONAL_YEARLY'),
            ],
            'business' => [
                'monthly' => env('STRIPE_PRICE_BUSINESS_MONTHLY'),
                'yearly' => env('STRIPE_PRICE_BUSINESS_YEARLY'),
            ],
        ];

        $updated = 0;
        $missing = [];

        foreach ($mapping as $slug => $prices) {
            $package = Package::where('slug', $slug)->first();
            if (!$package) {
                $this->warn("Package [{$slug}] not found — run PackageSeeder first.");
                continue;
            }

            $changes = [];
            if ($prices['monthly']) {
                $changes['stripe_price_id_monthly'] = $prices['monthly'];
            } else {
                $missing[] = 'STRIPE_PRICE_'.strtoupper($slug).'_MONTHLY';
            }
            if ($prices['yearly']) {
                $changes['stripe_price_id_yearly'] = $prices['yearly'];
            } else {
                $missing[] = 'STRIPE_PRICE_'.strtoupper($slug).'_YEARLY';
            }

            if ($changes === []) {
                continue;
            }

            $package->update($changes);
            $updated++;
            $this->info("Updated {$package->name}: ".implode(', ', array_keys($changes)));
        }

        if ($missing !== []) {
            $this->warn('Missing env vars (checkout will fail for those intervals):');
            foreach (array_unique($missing) as $key) {
                $this->line("  - {$key}");
            }
        }

        if ($updated === 0) {
            $this->warn('No package Stripe price IDs were updated.');
            return self::FAILURE;
        }

        $this->info("Synced {$updated} package(s).");
        return self::SUCCESS;
    }
}
