<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('commission_monthly', 5, 4)->nullable()->after('price_yearly');
            $table->decimal('commission_yearly', 5, 4)->nullable()->after('commission_monthly');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('interval')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('interval');
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['commission_monthly', 'commission_yearly']);
        });
    }
};
