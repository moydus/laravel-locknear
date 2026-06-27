<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_availability', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_availability', 'auto_accept')) {
                $table->boolean('auto_accept')->default(false)->after('active_jobs_count');
            }

            if (!Schema::hasColumn('provider_availability', 'accept_timeout_seconds')) {
                $table->unsignedSmallInteger('accept_timeout_seconds')->default(60)->after('auto_accept');
            }

            if (!Schema::hasColumn('provider_availability', 'pricing_filters')) {
                $table->json('pricing_filters')->nullable()->after('weekly_hours');
            }
        });
    }

    public function down(): void
    {
        Schema::table('provider_availability', function (Blueprint $table) {
            $table->dropColumn([
                'auto_accept',
                'accept_timeout_seconds',
                'pricing_filters',
            ]);
        });
    }
};
