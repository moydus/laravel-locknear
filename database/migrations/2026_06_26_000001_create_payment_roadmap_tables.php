<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('address');
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->index(['zip', 'state']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_payment_method_id')->unique();
            $table->string('type')->default('card');
            $table->string('brand')->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedSmallInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_default']);
            $table->index(['company_id', 'is_default']);
        });

        Schema::create('service_types', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->string('market')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->decimal('minimum_amount', 10, 2);
            $table->decimal('estimated_min_amount', 10, 2);
            $table->decimal('estimated_max_amount', 10, 2);
            $table->decimal('authorization_amount', 10, 2)->nullable();
            $table->decimal('commission_rate', 5, 4)->default(0.1500);
            $table->string('currency', 3)->default('usd');
            $table->string('algorithm_version')->default('v1');
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['service_type_id', 'is_active']);
            $table->index(['state', 'city', 'zip']);
            $table->index(['market', 'is_active']);
        });

        Schema::create('provider_service_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('radius_miles')->default(25);
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
            $table->index(['zip', 'state']);
        });

        Schema::create('provider_service_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'service_type_id']);
            $table->index(['service_type_id', 'is_active']);
        });

        Schema::create('provider_availability', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_online')->default(false);
            $table->boolean('is_24_7')->default(false);
            $table->unsignedSmallInteger('max_concurrent_jobs')->default(1);
            $table->unsignedSmallInteger('active_jobs_count')->default(0);
            $table->json('weekly_hours')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_availability_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'started_at']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('provider_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('period_date');
            $table->unsignedInteger('offers_sent')->default(0);
            $table->unsignedInteger('offers_accepted')->default(0);
            $table->unsignedInteger('offers_declined')->default(0);
            $table->unsignedInteger('offers_expired')->default(0);
            $table->unsignedInteger('jobs_completed')->default(0);
            $table->unsignedInteger('jobs_cancelled')->default(0);
            $table->unsignedInteger('no_shows')->default(0);
            $table->decimal('acceptance_rate', 5, 2)->default(0);
            $table->decimal('cancellation_rate', 5, 2)->default(0);
            $table->decimal('completion_rate', 5, 2)->default(0);
            $table->unsignedInteger('average_response_seconds')->nullable();
            $table->unsignedInteger('average_eta_minutes')->nullable();
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->decimal('quality_score', 5, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'period_date']);
            $table->index(['quality_score', 'period_date']);
        });

        Schema::create('provider_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->string('license_plate')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        Schema::create('provider_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('file_url')->nullable();
            $table->string('document_number')->nullable();
            $table->string('issuing_state', 2)->nullable();
            $table->string('verification_provider')->nullable();
            $table->text('verification_notes')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type', 'status']);
        });

        Schema::create('provider_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('available_balance', 10, 2)->default(0);
            $table->decimal('pending_balance', 10, 2)->default(0);
            $table->string('currency', 3)->default('usd');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_payout_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('processor')->default('stripe');
            $table->string('stripe_account_id')->unique()->nullable();
            $table->string('status')->default('not_started');
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->timestamp('onboarded_at')->nullable();
            $table->json('requirements')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_address_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('requested');
            $table->decimal('estimated_min_amount', 8, 2)->nullable();
            $table->decimal('estimated_max_amount', 8, 2)->nullable();
            $table->decimal('final_amount', 8, 2)->nullable();
            $table->string('currency', 3)->default('usd');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('booking_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('source')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'changed_at']);
            $table->index(['to_status', 'changed_at']);
        });

        Schema::create('pricing_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('pricing_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_type_slug')->nullable();
            $table->string('market')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->decimal('minimum_amount', 10, 2)->nullable();
            $table->decimal('estimated_min_amount', 10, 2);
            $table->decimal('estimated_max_amount', 10, 2);
            $table->decimal('authorization_amount', 10, 2)->nullable();
            $table->decimal('commission_rate', 5, 4)->default(0.1500);
            $table->string('currency', 3)->default('usd');
            $table->string('algorithm_version')->default('v1');
            $table->json('inputs')->nullable();
            $table->json('breakdown')->nullable();
            $table->timestamps();

            $table->index(['service_type_slug', 'state', 'city']);
            $table->index(['algorithm_version', 'created_at']);
        });

        Schema::create('service_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('en_route_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->string('no_show_party')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('dispatch_strategies', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->decimal('distance_weight', 6, 4)->default(0);
            $table->decimal('eta_weight', 6, 4)->default(0);
            $table->decimal('acceptance_rate_weight', 6, 4)->default(0);
            $table->decimal('rating_weight', 6, 4)->default(0);
            $table->decimal('cancellation_rate_weight', 6, 4)->default(0);
            $table->decimal('availability_weight', 6, 4)->default(0);
            $table->unsignedSmallInteger('max_parallel_offers')->default(3);
            $table->unsignedSmallInteger('offer_ttl_seconds')->default(60);
            $table->json('rules')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'created_at']);
        });

        Schema::create('dispatch_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispatch_strategy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dispatch_strategy_version')->nullable();
            $table->string('status')->default('searching');
            $table->unsignedSmallInteger('search_radius_miles')->default(25);
            $table->unsignedSmallInteger('max_parallel_offers')->default(3);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('dispatch_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('sent');
            $table->decimal('distance_miles', 8, 2)->nullable();
            $table->unsignedSmallInteger('eta_minutes')->nullable();
            $table->decimal('dispatch_score', 8, 4)->nullable();
            $table->json('score_breakdown')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->unsignedInteger('response_time_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['dispatch_request_id', 'company_id']);
            $table->index(['company_id', 'status']);
            $table->index(['dispatch_request_id', 'status']);
        });

        Schema::create('dispatch_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('dispatch_offer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['dispatch_request_id', 'type']);
        });

        Schema::create('job_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('assigned');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['job_id', 'company_id']);
            $table->index(['company_id', 'status']);
        });

        Schema::create('job_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedSmallInteger('eta_minutes')->nullable();
            $table->string('status')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'recorded_at']);
        });

        Schema::create('job_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('service_jobs')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('source')->nullable();
            $table->timestamp('changed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['job_id', 'changed_at']);
            $table->index(['to_status', 'changed_at']);
        });

        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payer_type')->default('customer');
            $table->string('purpose');
            $table->string('status')->default('requires_payment_method');
            $table->decimal('amount', 10, 2);
            $table->decimal('captured_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('processor')->default('stripe');
            $table->string('processor_intent_id')->unique()->nullable();
            $table->string('processor_charge_id')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'purpose']);
            $table->index(['company_id', 'purpose', 'status']);
            $table->index(['purpose', 'status']);
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->string('currency', 3)->default('usd');
            $table->string('processor')->default('stripe');
            $table->string('processor_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'type']);
            $table->index(['company_id', 'type', 'status']);
            $table->index(['type', 'processed_at']);
        });

        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('service_jobs')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('rate', 5, 4)->default(0.1500);
            $table->decimal('service_total', 10, 2);
            $table->decimal('platform_fee', 10, 2);
            $table->decimal('provider_amount', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('tip_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('usd');
            $table->string('status')->default('pending');
            $table->timestamp('collected_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['booking_id', 'status']);
        });

        Schema::create('tips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('service_jobs')->nullOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 8, 2);
            $table->string('currency', 3)->default('usd');
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_intent_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('usd');
            $table->string('reason')->nullable();
            $table->string('status')->default('pending');
            $table->string('processor_refund_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['booking_id', 'status']);
            $table->index(['status', 'processed_at']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->decimal('gross_amount', 10, 2);
            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2);
            $table->string('currency', 3)->default('usd');
            $table->string('processor')->default('stripe');
            $table->string('stripe_account_id')->nullable();
            $table->string('stripe_transfer_id')->nullable();
            $table->string('stripe_payout_id')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('available_on')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['status', 'available_on']);
        });

        Schema::create('provider_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('usd');
            $table->string('status')->default('posted');
            $table->timestamp('posted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type']);
            $table->index(['provider_wallet_id', 'posted_at']);
        });

        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('type');
            $table->string('severity')->default('medium');
            $table->string('status')->default('open');
            $table->decimal('score', 6, 2)->nullable();
            $table->text('reason')->nullable();
            $table->json('signals')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['type', 'status']);
            $table->index(['severity', 'created_at']);
        });

        Schema::create('market_demand_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('market')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->timestamp('window_start');
            $table->timestamp('window_end');
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('completed_count')->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->unsignedInteger('online_provider_count')->default(0);
            $table->decimal('average_eta_minutes', 8, 2)->nullable();
            $table->decimal('average_price', 10, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['market', 'window_start']);
            $table->index(['zip', 'window_start']);
            $table->index(['service_type_id', 'window_start']);
        });

        Schema::create('domain_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->foreignId('booking_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('job_id')->nullable()->constrained('service_jobs')->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('aggregate_type')->nullable();
            $table->unsignedBigInteger('aggregate_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['event_type', 'occurred_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
            $table->index(['booking_id', 'occurred_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->string('action');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['actor_type', 'actor_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('domain_events');
        Schema::dropIfExists('market_demand_snapshots');
        Schema::dropIfExists('fraud_flags');
        Schema::dropIfExists('provider_wallet_transactions');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('tips');
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_intents');
        Schema::dropIfExists('job_status_history');
        Schema::dropIfExists('job_tracking');
        Schema::dropIfExists('job_assignments');
        Schema::dropIfExists('dispatch_events');
        Schema::dropIfExists('dispatch_offers');
        Schema::dropIfExists('dispatch_requests');
        Schema::dropIfExists('dispatch_strategies');
        Schema::dropIfExists('service_jobs');
        Schema::dropIfExists('pricing_snapshots');
        Schema::dropIfExists('booking_status_history');
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('provider_payout_accounts');
        Schema::dropIfExists('provider_wallets');
        Schema::dropIfExists('provider_documents');
        Schema::dropIfExists('provider_vehicles');
        Schema::dropIfExists('provider_performance_metrics');
        Schema::dropIfExists('provider_availability_history');
        Schema::dropIfExists('provider_availability');
        Schema::dropIfExists('provider_service_types');
        Schema::dropIfExists('provider_service_areas');
        Schema::dropIfExists('pricing_rules');
        Schema::dropIfExists('service_types');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customer_profiles');
    }
};
