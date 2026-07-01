<?php

namespace Tests\Feature;

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncPackagePricesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_syncs_stripe_price_ids_from_env(): void
    {
        $this->seed(\Database\Seeders\PackageSeeder::class);

        putenv('STRIPE_PRICE_PROFESSIONAL_MONTHLY=price_pro_mo_test');
        putenv('STRIPE_PRICE_PROFESSIONAL_YEARLY=price_pro_yr_test');
        putenv('STRIPE_PRICE_BUSINESS_MONTHLY=price_bus_mo_test');
        putenv('STRIPE_PRICE_BUSINESS_YEARLY=price_bus_yr_test');

        $this->artisan('locknear:sync-package-prices')->assertSuccessful();

        $professional = Package::where('slug', 'professional')->first();
        $this->assertSame('price_pro_mo_test', $professional->stripe_price_id_monthly);
        $this->assertSame('price_pro_yr_test', $professional->stripe_price_id_yearly);

        $business = Package::where('slug', 'business')->first();
        $this->assertSame('price_bus_mo_test', $business->stripe_price_id_monthly);
        $this->assertSame('price_bus_yr_test', $business->stripe_price_id_yearly);
    }
}
