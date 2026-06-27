<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'provider_response')) {
                $table->text('provider_response')->nullable()->after('body');
            }

            if (!Schema::hasColumn('reviews', 'provider_responded_at')) {
                $table->timestamp('provider_responded_at')->nullable()->after('provider_response');
            }
        });

        Schema::table('provider_account_users', function (Blueprint $table) {
            if (Schema::hasColumn('provider_account_users', 'user_id')) {
                $table->foreignId('user_id')->nullable()->change();
            }

            if (!Schema::hasColumn('provider_account_users', 'email')) {
                $table->string('email')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['provider_response', 'provider_responded_at']);
        });

        Schema::table('provider_account_users', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
