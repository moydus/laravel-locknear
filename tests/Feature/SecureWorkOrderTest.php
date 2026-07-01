<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\PaymentIntent;
use App\Models\User;
use App\Models\WorkOrderQuote;
use App\Models\WorkOrderSignature;
use App\Services\PaymentEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class SecureWorkOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('work_orders.enabled', true);
        config()->set('work_orders.dispatch_fee_cents', 3900);
        config()->set('work_orders.minimum_service_authorization_cents', 9500);
        config()->set('services.api_key', 'test-api-key');
        Bus::fake();
        Event::fake();
    }

    public function test_dispatch_fee_is_server_controlled_and_intent_is_bound_once(): void
    {
        $token = str_repeat('a', 64);
        $intent = $this->paymentIntent($token);
        $payments = Mockery::mock(PaymentEngine::class);
        $payments->shouldReceive('authorize')->once()->andReturn(['requires_capture' => true]);
        $this->app->instance(PaymentEngine::class, $payments);

        $response = $this->withHeader('X-API-Key', 'test-api-key')->postJson('/api/leads', $this->leadPayload($intent, $token, [
            'dispatch_fee_cents' => 0,
        ]));

        $response->assertCreated();
        $lead = Lead::findOrFail($response->json('lead_id'));
        $this->assertSame(3900, $lead->dispatch_fee_cents);
        $this->assertSame($lead->id, $intent->fresh()->lead_id);
        $this->assertDatabaseHas('bookings', ['lead_id' => $lead->id]);

        $this->withHeader('X-API-Key', 'test-api-key')
            ->postJson('/api/leads', $this->leadPayload($intent, $token))
            ->assertUnprocessable();
    }

    public function test_foreign_payment_authorization_token_is_rejected(): void
    {
        $token = str_repeat('b', 64);
        $intent = $this->paymentIntent($token);
        $payments = Mockery::mock(PaymentEngine::class);
        $payments->shouldReceive('authorize')->once()->andReturn(['requires_capture' => true]);
        $this->app->instance(PaymentEngine::class, $payments);

        $this->withHeader('X-API-Key', 'test-api-key')
            ->postJson('/api/leads', $this->leadPayload($intent, str_repeat('c', 64)))
            ->assertUnprocessable();
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_only_latest_quote_can_be_approved_and_work_cannot_start_without_approval(): void
    {
        [$user, $company, $lead, $assignment, $intent] = $this->providerScenario('arrived');
        $old = WorkOrderQuote::create($this->quotePayload($lead, $company, $assignment, 1, 'pending'));
        $latest = WorkOrderQuote::create($this->quotePayload($lead, $company, $assignment, 2, 'pending'));

        $this->postJson("/api/track/{$lead->customer_token}/quotes/{$old->id}/approve")->assertConflict();
        $this->postJson("/api/track/{$lead->customer_token}/quotes/{$latest->id}/approve")->assertOk();
        $this->assertSame('approved', $latest->fresh()->status);
        $this->assertSame('superseded', $old->fresh()->status);

        Sanctum::actingAs($user);
        $this->postJson("/api/jobs/{$lead->id}/start")->assertOk();
        $this->assertSame('in_progress', $assignment->fresh()->status);
    }

    public function test_completion_requires_signature_and_captures_only_approved_total(): void
    {
        [$user, $company, $lead, $assignment, $intent] = $this->providerScenario('in_progress');
        $lead->update(['status' => 'in_progress']);
        $quote = WorkOrderQuote::create($this->quotePayload($lead, $company, $assignment, 1, 'approved'));
        Sanctum::actingAs($user);

        $this->postJson("/api/jobs/{$lead->id}/complete")->assertConflict();

        WorkOrderSignature::create([
            'lead_id' => $lead->id,
            'work_order_quote_id' => $quote->id,
            'signer_name' => 'Customer Test',
            'disk' => 'local',
            'path' => 'test/signature.png',
            'sha256' => str_repeat('d', 64),
            'consent_version' => 'work_order_completion_v1',
            'signed_at' => now(),
        ]);
        $payments = Mockery::mock(PaymentEngine::class);
        $payments->shouldReceive('capture')->once()->with(Mockery::on(fn (array $payload) => $payload['amount_to_capture_cents'] === 13900))->andReturn([
            'status' => 'succeeded',
            'captured_amount_cents' => 13900,
        ]);
        $this->app->instance(PaymentEngine::class, $payments);

        $this->postJson("/api/jobs/{$lead->id}/complete")->assertOk();
        $this->assertDatabaseHas('work_order_invoices', ['lead_id' => $lead->id, 'total_cents' => 13900]);
        $this->assertSame('completed', $lead->fresh()->status);
    }

    public function test_stale_provider_assignment_releases_hold_and_marks_no_show(): void
    {
        [, , $lead, $assignment, $intent] = $this->providerScenario('accepted');
        $assignment->update(['accepted_at' => now()->subMinutes(90)]);
        $payments = Mockery::mock(PaymentEngine::class);
        $payments->shouldReceive('cancel')->once()->with(Mockery::on(fn (array $payload) => $payload['payment_intent_id'] === $intent->id));
        $this->app->instance(PaymentEngine::class, $payments);

        $this->artisan('locknear:release-provider-no-shows')->assertSuccessful();

        $this->assertSame('provider_no_show', $assignment->fresh()->status);
        $this->assertSame('cancelled', $lead->fresh()->status);
        $this->assertSame('provider_no_show', $lead->fresh()->customer_cancellation_reason);
    }

    private function paymentIntent(string $token): PaymentIntent
    {
        return PaymentIntent::create([
            'idempotency_key' => 'test-'.$token,
            'payer_type' => 'customer',
            'purpose' => 'service_authorization',
            'status' => 'requires_capture',
            'amount' => '134.00',
            'amount_cents' => 13400,
            'captured_amount' => '0.00',
            'captured_amount_cents' => 0,
            'currency' => 'usd',
            'processor' => 'stripe',
            'processor_intent_id' => 'pi_'.substr($token, 0, 16),
            'authorized_at' => now(),
            'metadata' => [
                'authorization_token_hash' => hash('sha256', $token),
                'service_type' => 'car-lockout',
                'estimated_min' => 65,
                'estimated_max' => 95,
            ],
        ]);
    }

    private function leadPayload(PaymentIntent $intent, string $token, array $overrides = []): array
    {
        return array_merge([
            'zip' => '77001',
            'service_type' => 'car-lockout',
            'phone' => '7135550100',
            'payment_intent_id' => $intent->id,
            'payment_authorization_token' => $token,
            'authorization_confirmed' => true,
            'dispatch_fee_acknowledged' => true,
            'vehicle_owned_or_authorized' => true,
            'registration_available' => true,
            'photo_id_available' => true,
            'document_names_match' => true,
        ], $overrides);
    }

    private function providerScenario(string $assignmentStatus): array
    {
        $user = User::factory()->create(['role' => User::ROLE_BUSINESS]);
        $company = Company::create([
            'user_id' => $user->id,
            'name' => 'Test Locksmith',
            'slug' => 'test-locksmith-'.$assignmentStatus,
            'is_active' => true,
        ]);
        $lead = Lead::create([
            'zip' => '77001',
            'service_type' => 'car-lockout',
            'phone' => '7135550100',
            'status' => $assignmentStatus === 'in_progress' ? 'in_progress' : 'assigned',
            'customer_token' => str_repeat('t', 47).($assignmentStatus === 'in_progress' ? '1' : '2'),
            'work_order_number' => 'WO-TEST'.strtoupper(substr($assignmentStatus, 0, 3)),
            'dispatch_fee_cents' => 3900,
            'dispatch_fee_currency' => 'usd',
            'dispatch_fee_acknowledged' => true,
        ]);
        $assignment = LeadAssignment::create([
            'lead_id' => $lead->id,
            'company_id' => $company->id,
            'status' => $assignmentStatus,
            'arrived_at' => now(),
        ]);
        $intent = $this->paymentIntent(str_repeat($assignmentStatus === 'in_progress' ? 'e' : 'f', 64));
        $intent->update(['lead_id' => $lead->id, 'company_id' => $company->id, 'amount' => '289.00', 'amount_cents' => 28900]);

        return [$user, $company, $lead, $assignment, $intent];
    }

    private function quotePayload(Lead $lead, Company $company, LeadAssignment $assignment, int $version, string $status): array
    {
        return [
            'lead_id' => $lead->id,
            'company_id' => $company->id,
            'lead_assignment_id' => $assignment->id,
            'dispatch_fee_cents' => 3900,
            'service_fee_cents' => 10000,
            'total_cents' => 13900,
            'currency' => 'usd',
            'description' => 'Labor and parts',
            'status' => $status,
            'version' => $version,
            'proposed_at' => now(),
            'approved_at' => $status === 'approved' ? now() : null,
        ];
    }
}
