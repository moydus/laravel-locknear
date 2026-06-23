<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_claimed')->default(false)->after('is_active');
            $table->timestamp('claimed_at')->nullable()->after('is_claimed');
            $table->string('claim_token', 64)->unique()->nullable()->after('claimed_at');
            $table->string('source')->nullable()->after('claim_token'); // google_maps, manual, claimed
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['is_claimed', 'claimed_at', 'claim_token', 'source']);
        });
    }
};
