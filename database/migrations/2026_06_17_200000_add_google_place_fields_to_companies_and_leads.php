<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('google_place_id')->nullable()->after('zip');
            $table->string('formatted_address')->nullable()->after('google_place_id');
            $table->json('address_components')->nullable()->after('formatted_address');
            $table->string('place_source')->nullable()->after('address_components');
            $table->timestamp('place_verified_at')->nullable()->after('place_source');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('google_place_id')->nullable()->after('state');
            $table->string('formatted_address')->nullable()->after('google_place_id');
            $table->json('address_components')->nullable()->after('formatted_address');
            $table->string('place_source')->nullable()->after('address_components');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'google_place_id',
                'formatted_address',
                'address_components',
                'place_source',
                'place_verified_at',
            ]);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'google_place_id',
                'formatted_address',
                'address_components',
                'place_source',
            ]);
        });
    }
};
