<?php

use App\Models\Company;
use App\Models\Package;
use App\Models\Subscription;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $packageId = Package::query()->where('slug', 'free')->value('id')
            ?? Package::query()->where('is_active', true)->orderBy('sort_order')->value('id');

        if (!$packageId) {
            return;
        }

        Company::query()
            ->where('is_claimed', true)
            ->whereDoesntHave('subscription', fn ($q) => $q->whereIn('status', ['active', 'trialing']))
            ->select('id')
            ->chunkById(100, function ($companies) use ($packageId) {
                foreach ($companies as $company) {
                    Subscription::create([
                        'company_id' => $company->id,
                        'package_id' => $packageId,
                        'status' => 'trialing',
                        'interval' => 'monthly',
                        'trial_ends_at' => now()->addYear(),
                        'current_period_start' => now(),
                        'current_period_end' => now()->addYear(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Non-destructive backfill.
    }
};
