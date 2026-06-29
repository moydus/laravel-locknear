<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'authorization_confirmed')) {
                $table->boolean('authorization_confirmed')->default(false)->after('place_source');
            }

            if (!Schema::hasColumn('leads', 'authorization_confirmed_at')) {
                $table->timestamp('authorization_confirmed_at')->nullable()->after('authorization_confirmed');
            }

            if (!Schema::hasColumn('leads', 'authorization_disclaimer_version')) {
                $table->string('authorization_disclaimer_version')->nullable()->after('authorization_confirmed_at');
            }

            if (!Schema::hasColumn('leads', 'vehicle_make')) {
                $table->string('vehicle_make')->nullable()->after('authorization_disclaimer_version');
            }

            if (!Schema::hasColumn('leads', 'vehicle_model')) {
                $table->string('vehicle_model')->nullable()->after('vehicle_make');
            }

            if (!Schema::hasColumn('leads', 'vehicle_year')) {
                $table->string('vehicle_year', 10)->nullable()->after('vehicle_model');
            }

            if (!Schema::hasColumn('leads', 'vehicle_color')) {
                $table->string('vehicle_color')->nullable()->after('vehicle_year');
            }

            if (!Schema::hasColumn('leads', 'license_plate')) {
                $table->string('license_plate', 32)->nullable()->after('vehicle_color');
            }
        });

        Schema::table('lead_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_assignments', 'verification_checklist')) {
                $table->json('verification_checklist')->nullable()->after('last_location_at');
            }

            if (!Schema::hasColumn('lead_assignments', 'verification_checked_at')) {
                $table->timestamp('verification_checked_at')->nullable()->after('verification_checklist');
            }

            if (!Schema::hasColumn('lead_assignments', 'verification_notes')) {
                $table->text('verification_notes')->nullable()->after('verification_checked_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_assignments', function (Blueprint $table) {
            foreach (['verification_notes', 'verification_checked_at', 'verification_checklist'] as $column) {
                if (Schema::hasColumn('lead_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            foreach ([
                'license_plate',
                'vehicle_color',
                'vehicle_year',
                'vehicle_model',
                'vehicle_make',
                'authorization_disclaimer_version',
                'authorization_confirmed_at',
                'authorization_confirmed',
            ] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
