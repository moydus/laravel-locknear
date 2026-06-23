<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->timestamp('accepted_at')->nullable()->after('responded_at');
            $table->timestamp('en_route_at')->nullable()->after('accepted_at');
            $table->timestamp('arrived_at')->nullable()->after('en_route_at');
            $table->timestamp('completed_at')->nullable()->after('arrived_at');
            $table->decimal('provider_latitude', 10, 7)->nullable()->after('completed_at');
            $table->decimal('provider_longitude', 10, 7)->nullable()->after('provider_latitude');
            $table->timestamp('last_location_at')->nullable()->after('provider_longitude');
        });
    }

    public function down(): void
    {
        Schema::table('lead_assignments', function (Blueprint $table) {
            $table->dropColumn([
                'accepted_at',
                'en_route_at',
                'arrived_at',
                'completed_at',
                'provider_latitude',
                'provider_longitude',
                'last_location_at',
            ]);
        });
    }
};
