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
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Claim your business, receive jobs, and get paid through LockNear.',
                'price_monthly' => 0.00,
                'price_yearly' => null,
                'commission_monthly' => 0.2000,
                'commission_yearly' => 0.2000,
                'stripe_price_id_monthly' => null,
                'stripe_price_id_yearly' => null,
                'max_leads_per_month' => null,
                'features' => ['Claim business', 'Reviews', 'Analytics', 'Receive jobs', 'Stripe payouts', 'Basic profile'],
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Lower your commission and get stronger dispatch visibility.',
                'price_monthly' => 299.00,
                'price_yearly' => 2990.00,
                'commission_monthly' => 0.1500,
                'commission_yearly' => 0.1300,
                'stripe_price_id_monthly' => env('STRIPE_PRICE_PROFESSIONAL_MONTHLY'),
                'stripe_price_id_yearly' => env('STRIPE_PRICE_PROFESSIONAL_YEARLY'),
                'max_leads_per_month' => null,
                'features' => ['15% monthly commission', '13% annual commission', 'Preferred partner badge', 'Priority dispatch', 'Advanced analytics'],
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'For teams that want lower commission and multi-user operations.',
                'price_monthly' => 699.00,
                'price_yearly' => 6990.00,
                'commission_monthly' => 0.1000,
                'commission_yearly' => 0.0800,
                'stripe_price_id_monthly' => env('STRIPE_PRICE_BUSINESS_MONTHLY'),
                'stripe_price_id_yearly' => env('STRIPE_PRICE_BUSINESS_YEARLY'),
                'max_leads_per_month' => null,
                'features' => ['10% monthly commission', '8% annual commission', 'Team management', 'Coverage analytics', 'Priority support'],
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Custom volume, market, and partner API agreements.',
                'price_monthly' => 0.00,
                'price_yearly' => null,
                'commission_monthly' => 0.0500,
                'commission_yearly' => 0.0500,
                'stripe_price_id_monthly' => null,
                'stripe_price_id_yearly' => null,
                'max_leads_per_month' => null,
                'features' => ['Custom commission', 'Partner API', 'Dedicated support', 'Market-level reporting'],
                'is_active' => true,
                'sort_order' => 4,
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
