<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_identities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('external_id')->nullable();
            $table->string('google_place_id')->nullable();
            $table->string('apple_place_id')->nullable();
            $table->string('yelp_business_id')->nullable();
            $table->string('website')->nullable();
            $table->string('phone_normalized')->nullable();
            $table->decimal('match_confidence', 5, 2)->default(0);
            $table->string('status')->default('candidate');
            $table->timestamp('matched_at')->nullable();
            $table->json('match_signals')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['source', 'external_id']);
            $table->index('google_place_id');
            $table->index('apple_place_id');
            $table->index('yelp_business_id');
            $table->index('phone_normalized');
            $table->index(['company_id', 'status']);
        });

        Schema::create('provider_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('display_name')->nullable();
            $table->string('timezone')->default('America/New_York');
            $table->unsignedSmallInteger('default_capacity')->default(1);
            $table->json('capabilities')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('provider_account_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner');
            $table->string('status')->default('active');
            $table->json('permissions')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
            $table->index(['company_id', 'role', 'status']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('provider_growth_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('score')->default(0);
            $table->unsignedSmallInteger('profile_completion_percent')->default(0);
            $table->boolean('claim_completed')->default(false);
            $table->boolean('verified')->default(false);
            $table->boolean('online_enabled')->default(false);
            $table->boolean('has_photo')->default(false);
            $table->boolean('insurance_uploaded')->default(false);
            $table->boolean('first_job_completed')->default(false);
            $table->boolean('five_reviews_reached')->default(false);
            $table->json('breakdown')->nullable();
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->index(['score', 'calculated_at']);
            $table->index(['profile_completion_percent', 'calculated_at']);
        });

        Schema::create('provider_crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outreach_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('open');
            $table->string('outcome')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('next_follow_up_at');
        });

        Schema::create('market_expansion_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('period_date');
            $table->string('market')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->unsignedInteger('directory_provider_count')->default(0);
            $table->unsignedInteger('claimed_provider_count')->default(0);
            $table->unsignedInteger('verified_provider_count')->default(0);
            $table->unsignedInteger('online_provider_count')->default(0);
            $table->unsignedInteger('booking_demand_count')->default(0);
            $table->decimal('estimated_daily_demand', 10, 2)->default(0);
            $table->decimal('coverage_percent', 5, 2)->default(0);
            $table->string('recommendation')->default('watch');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['period_date', 'market', 'state', 'city', 'zip'], 'market_expansion_period_market_unique');
            $table->index(['recommendation', 'coverage_percent']);
            $table->index(['state', 'city', 'zip']);
        });

        Schema::create('provider_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('push_token')->unique();
            $table->string('device_id')->nullable();
            $table->string('app_version')->nullable();
            $table->string('status')->default('active');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_devices');
        Schema::dropIfExists('market_expansion_metrics');
        Schema::dropIfExists('provider_crm_activities');
        Schema::dropIfExists('provider_growth_scores');
        Schema::dropIfExists('provider_account_users');
        Schema::dropIfExists('provider_accounts');
        Schema::dropIfExists('company_identities');
    }
};
