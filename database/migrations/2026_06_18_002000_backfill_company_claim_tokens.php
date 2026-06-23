<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Company::query()
            ->whereNull('claim_token')
            ->where('is_claimed', false)
            ->each(function (Company $company) {
                $company->update(['claim_token' => $company->ensureClaimToken()]);
            });
    }

    public function down(): void
    {
        // no-op
    }
};
