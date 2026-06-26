<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domain_events', function (Blueprint $table) {
            if (!Schema::hasColumn('domain_events', 'event_name')) {
                $table->string('event_name')->nullable()->after('event_type');
            }

            if (!Schema::hasColumn('domain_events', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('occurred_at');
            }

            if (!Schema::hasColumn('domain_events', 'processing_status')) {
                $table->string('processing_status')->default('pending')->after('processed_at');
            }

            $table->index(['event_name', 'occurred_at'], 'domain_events_event_name_occurred_at_index');
            $table->index(['processing_status', 'occurred_at'], 'domain_events_processing_status_occurred_at_index');
        });

        Schema::create('dispatch_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('period_date');
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('market')->default('default');
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->unsignedInteger('booking_count')->default(0);
            $table->unsignedInteger('dispatch_started_count')->default(0);
            $table->unsignedInteger('offer_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('expired_count')->default(0);
            $table->unsignedInteger('redispatch_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->decimal('acceptance_rate', 5, 2)->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->decimal('average_eta_minutes', 8, 2)->nullable();
            $table->unsignedInteger('average_response_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['period_date', 'service_type_id', 'market', 'state', 'city', 'zip'], 'dispatch_daily_metrics_unique');
            $table->index(['period_date', 'market']);
            $table->index(['state', 'city', 'zip']);
        });

        Schema::create('provider_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('period_date');
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('offers_sent')->default(0);
            $table->unsignedInteger('offers_accepted')->default(0);
            $table->unsignedInteger('offers_declined')->default(0);
            $table->unsignedInteger('offers_expired')->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->unsignedInteger('jobs_cancelled')->default(0);
            $table->unsignedInteger('online_seconds')->default(0);
            $table->decimal('acceptance_rate', 5, 2)->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->unsignedInteger('average_response_seconds')->nullable();
            $table->unsignedInteger('average_eta_minutes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['period_date', 'company_id']);
            $table->index(['quality_score', 'period_date']);
            $table->index(['acceptance_rate', 'period_date']);
        });

        Schema::create('city_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('period_date');
            $table->string('market')->default('default');
            $table->string('city');
            $table->string('state', 2);
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('booking_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('directory_provider_count')->default(0);
            $table->unsignedInteger('claimed_provider_count')->default(0);
            $table->unsignedInteger('verified_provider_count')->default(0);
            $table->unsignedInteger('online_provider_count')->default(0);
            $table->decimal('coverage_percent', 5, 2)->default(0);
            $table->decimal('average_price', 10, 2)->nullable();
            $table->decimal('average_eta_minutes', 8, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['period_date', 'market', 'state', 'city', 'service_type_id'], 'city_daily_metrics_unique');
            $table->index(['state', 'city', 'period_date']);
            $table->index(['coverage_percent', 'period_date']);
        });

        Schema::create('zip_daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('period_date');
            $table->string('market')->default('default');
            $table->string('zip', 10);
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('booking_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('directory_provider_count')->default(0);
            $table->unsignedInteger('claimed_provider_count')->default(0);
            $table->unsignedInteger('verified_provider_count')->default(0);
            $table->unsignedInteger('online_provider_count')->default(0);
            $table->decimal('coverage_percent', 5, 2)->default(0);
            $table->decimal('average_price', 10, 2)->nullable();
            $table->decimal('average_eta_minutes', 8, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['period_date', 'market', 'zip', 'service_type_id'], 'zip_daily_metrics_unique');
            $table->index(['zip', 'period_date']);
            $table->index(['coverage_percent', 'period_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zip_daily_metrics');
        Schema::dropIfExists('city_daily_metrics');
        Schema::dropIfExists('provider_daily_metrics');
        Schema::dropIfExists('dispatch_daily_metrics');

        Schema::table('domain_events', function (Blueprint $table) {
            $table->dropIndex('domain_events_event_name_occurred_at_index');
            $table->dropIndex('domain_events_processing_status_occurred_at_index');
            $table->dropColumn(['event_name', 'processed_at', 'processing_status']);
        });
    }
};
