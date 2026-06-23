<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('customer_token', 64)->unique()->nullable()->after('source');
            $table->decimal('latitude', 10, 7)->nullable()->after('customer_token');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('customer_name')->nullable()->after('longitude');
            $table->string('city')->nullable()->after('customer_name');
            $table->string('state')->nullable()->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['customer_token', 'latitude', 'longitude', 'customer_name', 'city', 'state']);
        });
    }
};
