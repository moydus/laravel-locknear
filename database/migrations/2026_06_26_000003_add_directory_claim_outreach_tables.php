<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'lifecycle_status')) {
                $table->string('lifecycle_status')->default('imported')->after('provider_status')->index();
            }

            if (!Schema::hasColumn('companies', 'source_last_synced_at')) {
                $table->timestamp('source_last_synced_at')->nullable()->after('source');
            }
        });

        Schema::create('company_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('external_id')->nullable();
            $table->string('external_url')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->unsignedInteger('review_count')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'source', 'external_id']);
            $table->index(['source', 'last_synced_at']);
        });

        Schema::create('company_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('verification_method')->nullable();
            $table->string('verification_channel')->nullable();
            $table->string('verification_target')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('outreach_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('market')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('zip', 10)->nullable();
            $table->string('status')->default('draft');
            $table->string('channel_mix')->nullable();
            $table->unsignedInteger('target_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('claimed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['market', 'status']);
            $table->index(['state', 'city', 'zip']);
        });

        Schema::create('provider_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outreach_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('token', 80)->unique();
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['email', 'status']);
            $table->index(['phone', 'status']);
        });

        Schema::create('outreach_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outreach_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('provider_invitation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('recipient')->nullable();
            $table->string('status')->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->json('payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['outreach_campaign_id', 'status']);
            $table->index(['provider_invitation_id', 'status']);
            $table->index(['company_id', 'status']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outreach_messages');
        Schema::dropIfExists('provider_invitations');
        Schema::dropIfExists('outreach_campaigns');
        Schema::dropIfExists('company_claims');
        Schema::dropIfExists('company_sources');

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['lifecycle_status', 'source_last_synced_at']);
        });
    }
};
