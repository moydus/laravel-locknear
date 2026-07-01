<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('vin', 17)->nullable()->after('license_plate');
            $table->boolean('vehicle_owned_or_authorized')->nullable()->after('vin');
            $table->boolean('registration_available')->nullable()->after('vehicle_owned_or_authorized');
            $table->boolean('photo_id_available')->nullable()->after('registration_available');
            $table->boolean('document_names_match')->nullable()->after('photo_id_available');
            $table->timestamp('customer_cancelled_at')->nullable()->after('document_names_match');
            $table->string('customer_cancellation_reason')->nullable()->after('customer_cancelled_at');
        });

        Schema::create('work_order_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_assignment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('dispatch_fee_cents');
            $table->unsignedInteger('service_fee_cents');
            $table->unsignedInteger('total_cents');
            $table->string('currency', 3)->default('usd');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('proposed_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('approved_ip')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
            $table->unique(['lead_id', 'version']);
        });

        Schema::create('work_order_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('sha256', 64);
            $table->string('status')->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'type']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('work_order_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_quote_id')->constrained()->cascadeOnDelete();
            $table->string('signer_name');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('sha256', 64);
            $table->string('consent_version', 64);
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
        });

        Schema::create('work_order_location_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->string('event_type')->default('location_update');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['lead_id', 'recorded_at']);
        });

        Schema::create('work_order_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('actor_type');
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['lead_id', 'recorded_at']);
        });

        Schema::create('work_order_disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('public_id')->unique();
            $table->string('reason');
            $table->text('description');
            $table->string('status')->default('open');
            $table->string('resolution')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'status']);
        });

        Schema::create('work_order_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_quote_id')->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->unsignedInteger('dispatch_fee_cents');
            $table->unsignedInteger('service_fee_cents');
            $table->unsignedInteger('total_cents');
            $table->string('currency', 3)->default('usd');
            $table->string('payment_status')->default('paid');
            $table->timestamp('issued_at');
            $table->json('snapshot');
            $table->timestamps();
        });

        Schema::create('work_order_communications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('direction')->nullable();
            $table->string('provider')->nullable();
            $table->string('external_id')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['lead_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_communications');
        Schema::dropIfExists('work_order_invoices');
        Schema::dropIfExists('work_order_disputes');
        Schema::dropIfExists('work_order_status_events');
        Schema::dropIfExists('work_order_location_events');
        Schema::dropIfExists('work_order_signatures');
        Schema::dropIfExists('work_order_evidence');
        Schema::dropIfExists('work_order_quotes');

        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'vin', 'vehicle_owned_or_authorized', 'registration_available',
                'photo_id_available', 'document_names_match', 'customer_cancelled_at',
                'customer_cancellation_reason',
            ]);
        });
    }
};
