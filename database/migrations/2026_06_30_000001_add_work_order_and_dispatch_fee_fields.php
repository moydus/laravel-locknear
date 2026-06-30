<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'work_order_number')) {
                $table->string('work_order_number')->nullable()->unique()->after('public_id');
            }

            if (!Schema::hasColumn('leads', 'dispatch_fee_cents')) {
                $table->unsignedInteger('dispatch_fee_cents')->default(3900)->after('authorization_disclaimer_version');
            }

            if (!Schema::hasColumn('leads', 'dispatch_fee_currency')) {
                $table->string('dispatch_fee_currency', 3)->default('usd')->after('dispatch_fee_cents');
            }

            if (!Schema::hasColumn('leads', 'dispatch_fee_policy_version')) {
                $table->string('dispatch_fee_policy_version', 64)->nullable()->after('dispatch_fee_currency');
            }

            if (!Schema::hasColumn('leads', 'dispatch_fee_acknowledged')) {
                $table->boolean('dispatch_fee_acknowledged')->default(false)->after('dispatch_fee_policy_version');
            }

            if (!Schema::hasColumn('leads', 'dispatch_fee_acknowledged_at')) {
                $table->timestamp('dispatch_fee_acknowledged_at')->nullable()->after('dispatch_fee_acknowledged');
            }
        });

        Schema::table('lead_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_assignments', 'service_refusal_reason')) {
                $table->string('service_refusal_reason')->nullable()->after('verification_notes');
            }

            if (!Schema::hasColumn('lead_assignments', 'service_refused_at')) {
                $table->timestamp('service_refused_at')->nullable()->after('service_refusal_reason');
            }

            if (!Schema::hasColumn('lead_assignments', 'dispatch_fee_eligible')) {
                $table->boolean('dispatch_fee_eligible')->default(false)->after('service_refused_at');
            }

            if (!Schema::hasColumn('lead_assignments', 'dispatch_fee_capture_status')) {
                $table->string('dispatch_fee_capture_status')->nullable()->after('dispatch_fee_eligible');
            }

            if (!Schema::hasColumn('lead_assignments', 'dispatch_fee_capture_amount_cents')) {
                $table->unsignedInteger('dispatch_fee_capture_amount_cents')->nullable()->after('dispatch_fee_capture_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_assignments', function (Blueprint $table) {
            foreach ([
                'dispatch_fee_capture_amount_cents',
                'dispatch_fee_capture_status',
                'dispatch_fee_eligible',
                'service_refused_at',
                'service_refusal_reason',
            ] as $column) {
                if (Schema::hasColumn('lead_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'dispatch_fee_acknowledged_at',
                'dispatch_fee_acknowledged',
                'dispatch_fee_policy_version',
                'dispatch_fee_currency',
                'dispatch_fee_cents',
                'work_order_number',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
