<?php

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Company::query()
            ->whereRaw('LOWER(city) = ?', ['houston'])
            ->where('state', 'TX')
            ->where(function ($query) {
                $query->where('latitude', '>', 35)
                    ->orWhere('longitude', '>', -90);
            })
            ->update([
                'latitude' => 29.7604,
                'longitude' => -95.3698,
                'zip' => null,
                'service_areas' => json_encode(['Houston']),
            ]);
    }

    public function down(): void
    {
        // No-op: imported data correction.
    }
};
