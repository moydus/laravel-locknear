<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For solo locksmiths getting started on LockNear.',
                'price_monthly' => 49.00,
                'price_yearly' => 490.00,
                'stripe_price_id_monthly' => env('STRIPE_PRICE_STARTER_MONTHLY'),
                'stripe_price_id_yearly' => env('STRIPE_PRICE_STARTER_YEARLY'),
                'max_leads_per_month' => 25,
                'features' => ['25 leads / month', 'Provider panel', 'SMS dispatch'],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'For busy locksmiths who want more volume.',
                'price_monthly' => 99.00,
                'price_yearly' => 990.00,
                'stripe_price_id_monthly' => env('STRIPE_PRICE_PRO_MONTHLY'),
                'stripe_price_id_yearly' => env('STRIPE_PRICE_PRO_YEARLY'),
                'max_leads_per_month' => 100,
                'features' => ['100 leads / month', 'Priority dispatch', 'Reviews dashboard'],
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($packages as $package) {
            Package::updateOrCreate(
                ['slug' => $package['slug']],
                $package,
            );
        }
    }
}
