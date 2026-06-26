<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'public_id')) {
                $table->string('public_id')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('companies', 'provider_status')) {
                $table->string('provider_status')->default('pending')->after('is_active')->index();
            }

            if (!Schema::hasColumn('companies', 'timezone')) {
                $table->string('timezone')->default('America/New_York')->after('state');
            }

            $table->index(['state', 'city', 'zip'], 'companies_state_city_zip_index');
            $table->index(['latitude', 'longitude'], 'companies_latitude_longitude_index');
        });

        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'public_id')) {
                $table->string('public_id')->nullable()->unique()->after('id');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'public_id')) {
                $table->string('public_id')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('bookings', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('public_id');
            }

            if (!Schema::hasColumn('bookings', 'customer_timezone')) {
                $table->string('customer_timezone')->nullable()->after('currency');
            }

            if (!Schema::hasColumn('bookings', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('service_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('service_jobs', 'public_id')) {
                $table->string('public_id')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('service_jobs', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('public_id');
            }

            if (!Schema::hasColumn('service_jobs', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('dispatch_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('dispatch_requests', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('id');
            }
        });

        Schema::table('payment_intents', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_intents', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('payment_intents', 'amount_cents')) {
                $table->unsignedBigInteger('amount_cents')->nullable()->after('amount');
            }

            if (!Schema::hasColumn('payment_intents', 'captured_amount_cents')) {
                $table->unsignedBigInteger('captured_amount_cents')->default(0)->after('captured_amount');
            }
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_transactions', 'gross_amount_cents')) {
                $table->unsignedBigInteger('gross_amount_cents')->nullable()->after('gross_amount');
            }

            if (!Schema::hasColumn('payment_transactions', 'fee_amount_cents')) {
                $table->unsignedBigInteger('fee_amount_cents')->default(0)->after('fee_amount');
            }

            if (!Schema::hasColumn('payment_transactions', 'net_amount_cents')) {
                $table->unsignedBigInteger('net_amount_cents')->nullable()->after('net_amount');
            }
        });

        Schema::table('commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('commissions', 'service_total_cents')) {
                $table->unsignedBigInteger('service_total_cents')->nullable()->after('service_total');
            }

            if (!Schema::hasColumn('commissions', 'platform_fee_cents')) {
                $table->unsignedBigInteger('platform_fee_cents')->nullable()->after('platform_fee');
            }

            if (!Schema::hasColumn('commissions', 'provider_amount_cents')) {
                $table->unsignedBigInteger('provider_amount_cents')->nullable()->after('provider_amount');
            }

            if (!Schema::hasColumn('commissions', 'tax_amount_cents')) {
                $table->unsignedBigInteger('tax_amount_cents')->default(0)->after('tax_amount');
            }

            if (!Schema::hasColumn('commissions', 'tip_amount_cents')) {
                $table->unsignedBigInteger('tip_amount_cents')->default(0)->after('tip_amount');
            }

            if (!Schema::hasColumn('commissions', 'discount_amount_cents')) {
                $table->unsignedBigInteger('discount_amount_cents')->default(0)->after('discount_amount');
            }
        });

        Schema::table('payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('payouts', 'gross_amount_cents')) {
                $table->unsignedBigInteger('gross_amount_cents')->nullable()->after('gross_amount');
            }

            if (!Schema::hasColumn('payouts', 'fee_amount_cents')) {
                $table->unsignedBigInteger('fee_amount_cents')->default(0)->after('fee_amount');
            }

            if (!Schema::hasColumn('payouts', 'net_amount_cents')) {
                $table->unsignedBigInteger('net_amount_cents')->nullable()->after('net_amount');
            }
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing_rules', 'minimum_amount_cents')) {
                $table->unsignedBigInteger('minimum_amount_cents')->nullable()->after('minimum_amount');
            }

            if (!Schema::hasColumn('pricing_rules', 'estimated_min_amount_cents')) {
                $table->unsignedBigInteger('estimated_min_amount_cents')->nullable()->after('estimated_min_amount');
            }

            if (!Schema::hasColumn('pricing_rules', 'estimated_max_amount_cents')) {
                $table->unsignedBigInteger('estimated_max_amount_cents')->nullable()->after('estimated_max_amount');
            }

            if (!Schema::hasColumn('pricing_rules', 'authorization_amount_cents')) {
                $table->unsignedBigInteger('authorization_amount_cents')->nullable()->after('authorization_amount');
            }
        });

        Schema::table('pricing_snapshots', function (Blueprint $table) {
            if (!Schema::hasColumn('pricing_snapshots', 'minimum_amount_cents')) {
                $table->unsignedBigInteger('minimum_amount_cents')->nullable()->after('minimum_amount');
            }

            if (!Schema::hasColumn('pricing_snapshots', 'estimated_min_amount_cents')) {
                $table->unsignedBigInteger('estimated_min_amount_cents')->nullable()->after('estimated_min_amount');
            }

            if (!Schema::hasColumn('pricing_snapshots', 'estimated_max_amount_cents')) {
                $table->unsignedBigInteger('estimated_max_amount_cents')->nullable()->after('estimated_max_amount');
            }

            if (!Schema::hasColumn('pricing_snapshots', 'authorization_amount_cents')) {
                $table->unsignedBigInteger('authorization_amount_cents')->nullable()->after('authorization_amount');
            }
        });

        Schema::table('provider_service_areas', function (Blueprint $table) {
            if (!Schema::hasColumn('provider_service_areas', 'version')) {
                $table->unsignedInteger('version')->default(1)->after('is_active');
            }

            if (!Schema::hasColumn('provider_service_areas', 'effective_at')) {
                $table->timestamp('effective_at')->nullable()->after('version');
            }

            if (!Schema::hasColumn('provider_service_areas', 'retired_at')) {
                $table->timestamp('retired_at')->nullable()->after('effective_at');
            }

            $table->index(['latitude', 'longitude'], 'provider_service_areas_latitude_longitude_index');
        });

        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'speed_rating')) {
                $table->unsignedTinyInteger('speed_rating')->nullable()->after('rating');
            }

            if (!Schema::hasColumn('reviews', 'communication_rating')) {
                $table->unsignedTinyInteger('communication_rating')->nullable()->after('speed_rating');
            }

            if (!Schema::hasColumn('reviews', 'professionalism_rating')) {
                $table->unsignedTinyInteger('professionalism_rating')->nullable()->after('communication_rating');
            }

            if (!Schema::hasColumn('reviews', 'price_rating')) {
                $table->unsignedTinyInteger('price_rating')->nullable()->after('professionalism_rating');
            }

            if (!Schema::hasColumn('reviews', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['speed_rating', 'communication_rating', 'professionalism_rating', 'price_rating']);
        });

        Schema::table('provider_service_areas', function (Blueprint $table) {
            $table->dropIndex('provider_service_areas_latitude_longitude_index');
            $table->dropColumn(['version', 'effective_at', 'retired_at']);
        });

        Schema::table('pricing_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'minimum_amount_cents',
                'estimated_min_amount_cents',
                'estimated_max_amount_cents',
                'authorization_amount_cents',
            ]);
        });

        Schema::table('pricing_rules', function (Blueprint $table) {
            $table->dropColumn([
                'minimum_amount_cents',
                'estimated_min_amount_cents',
                'estimated_max_amount_cents',
                'authorization_amount_cents',
            ]);
        });

        Schema::table('payouts', function (Blueprint $table) {
            $table->dropColumn(['gross_amount_cents', 'fee_amount_cents', 'net_amount_cents']);
        });

        Schema::table('commissions', function (Blueprint $table) {
            $table->dropColumn([
                'service_total_cents',
                'platform_fee_cents',
                'provider_amount_cents',
                'tax_amount_cents',
                'tip_amount_cents',
                'discount_amount_cents',
            ]);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['gross_amount_cents', 'fee_amount_cents', 'net_amount_cents']);
        });

        Schema::table('payment_intents', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'amount_cents', 'captured_amount_cents']);
        });

        Schema::table('dispatch_requests', function (Blueprint $table) {
            $table->dropColumn('idempotency_key');
        });

        Schema::table('service_jobs', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['public_id', 'idempotency_key']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn(['public_id', 'idempotency_key', 'customer_timezone']);
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('public_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_state_city_zip_index');
            $table->dropIndex('companies_latitude_longitude_index');
            $table->dropColumn(['public_id', 'provider_status', 'timezone']);
        });
    }
};
