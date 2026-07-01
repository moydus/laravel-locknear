<?php

use App\Models\Company;
use App\Models\CompanyClaim;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Company::query()
            ->whereRaw('LOWER(city) = ?', ['houston'])
            ->where('state', 'PA')
            ->update(['state' => 'TX']);

        $legacyClaims = [
            'all-houston-locksmith' => 'JnhAqWNfCHopNDMlTYlqaodPDDRo4VLrzxREUQLrMCx8ZePI',
        ];

        foreach ($legacyClaims as $slug => $token) {
            $claim = CompanyClaim::query()
                ->where('verification_method', 'claim_token')
                ->whereHas('company', fn ($query) => $query->where('slug', $slug))
                ->first();

            if (!$claim) {
                continue;
            }

            $metadata = $claim->metadata ?? [];
            if (!empty($metadata['claim_token'])) {
                continue;
            }

            $metadata['claim_token'] = $token;
            $claim->update(['metadata' => $metadata]);
        }
    }

    public function down(): void
    {
        // No-op: imported data correction.
    }
};
