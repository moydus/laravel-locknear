<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingState;
use App\Events\DispatchStatusChanged;
use App\Events\LeadCompleted;
use App\Events\ProviderLocationUpdated;
use App\Exceptions\LeadBillingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\PaymentIntent;
use App\Models\WorkOrderInvoice;
use App\Models\WorkOrderQuote;
use App\Models\WorkOrderSignature;
use App\Services\DispatchFeeService;
use App\Services\DispatchService;
use App\Services\DomainEventRecorder;
use App\Services\LeadAcceptanceService;
use App\Services\PaymentEngine;
use App\Support\LeadPricing;
use App\Support\LockNearUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

class LeadController extends Controller
{
    private const AUTHORIZATION_REQUIRED_SERVICES = [
        'car-lockout',
        'car-key-replacement',
        'house-lockout',
        'lock-rekey',
        'commercial',
        'emergency',
        '24-hour-locksmith',
        'emergency-locksmith',
        'locked-keys-in-car',
        'lost-car-keys',
        'key-fob-programming',
        'ignition-repair',
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zip' => ['required', 'string', 'size:5', 'regex:/^[0-9]+$/'],
            'service_type' => ['required', 'string', 'in:car-lockout,car-key-replacement,house-lockout,lock-rekey,commercial,emergency,24-hour-locksmith,emergency-locksmith,locked-keys-in-car,lost-car-keys,key-fob-programming,ignition-repair'],
            'phone' => ['required_without:email', 'nullable', 'string', 'min:10'],
            'email' => ['required_without:phone', 'nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'customer_name' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:2'],
            'google_place_id' => ['nullable', 'string', 'max:255'],
            'formatted_address' => ['nullable', 'string', 'max:500'],
            'address_components' => ['nullable', 'array'],
            'place_source' => ['nullable', 'string', 'in:google,manual,gps'],
            'payment_intent_id' => [config('work_orders.enabled') ? 'required' : 'nullable', 'integer', 'exists:payment_intents,id'],
            'payment_authorization_token' => [config('work_orders.enabled') ? 'required' : 'nullable', 'string', 'min:32', 'max:255'],
            'authorization_confirmed' => ['nullable', 'boolean'],
            'authorization_disclaimer_version' => ['nullable', 'string', 'max:64'],
            'dispatch_fee_cents' => ['nullable', 'integer', 'min:0', 'max:25000'],
            'dispatch_fee_currency' => ['nullable', 'string', 'size:3'],
            'dispatch_fee_policy_version' => ['nullable', 'string', 'max:64'],
            'dispatch_fee_acknowledged' => ['nullable', 'boolean'],
            'vehicle_make' => ['nullable', 'string', 'max:100'],
            'vehicle_model' => ['nullable', 'string', 'max:100'],
            'vehicle_year' => ['nullable', 'string', 'max:10'],
            'vehicle_color' => ['nullable', 'string', 'max:100'],
            'license_plate' => ['nullable', 'string', 'max:32'],
            'vin' => ['nullable', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'],
            'vehicle_owned_or_authorized' => ['nullable', 'boolean'],
            'registration_available' => ['nullable', 'boolean'],
            'photo_id_available' => ['nullable', 'boolean'],
            'document_names_match' => ['nullable', 'boolean'],
            'preferred_company_slug' => ['nullable', 'string', 'max:255'],
            'provider_id' => ['nullable', 'string', 'max:255'],
        ]);

        if (
            in_array($validated['service_type'], self::AUTHORIZATION_REQUIRED_SERVICES, true)
            && ! $request->boolean('authorization_confirmed')
        ) {
            return response()->json([
                'message' => 'Authorization confirmation is required before dispatch.',
                'errors' => [
                    'authorization_confirmed' => [
                        'Please confirm you are the owner or authorized user of the vehicle or property.',
                    ],
                ],
            ], 422);
        }

        if (
            in_array($validated['service_type'], self::AUTHORIZATION_REQUIRED_SERVICES, true)
            && (int) config('work_orders.dispatch_fee_cents', 3900) > 0
            && ! $request->boolean('dispatch_fee_acknowledged')
        ) {
            return response()->json([
                'message' => 'Dispatch fee policy acknowledgement is required before dispatch.',
                'errors' => [
                    'dispatch_fee_acknowledged' => [
                        'Please acknowledge that a dispatch fee may apply if ownership or authorization cannot be verified after arrival.',
                    ],
                ],
            ], 422);
        }

        $dispatchFeeCents = (int) config('work_orders.dispatch_fee_cents', 3900);
        unset($validated['dispatch_fee_cents'], $validated['dispatch_fee_currency']);

        $authorizedIntent = null;
        if (config('work_orders.enabled')) {
            $authorizedIntent = PaymentIntent::query()
                ->whereKey($validated['payment_intent_id'])
                ->whereNull('lead_id')
                ->where('payer_type', 'customer')
                ->first();

            if (! $authorizedIntent) {
                return response()->json(['error' => 'A valid unused customer payment authorization is required.'], 422);
            }

            try {
                app(PaymentEngine::class)->authorize([
                    'payment_intent_id' => $authorizedIntent->id,
                    'idempotency_key' => 'verify_dispatch_authorization_'.$authorizedIntent->id,
                ]);
            } catch (RuntimeException $exception) {
                return response()->json(['error' => 'Payment authorization could not be verified.'], 422);
            }

            $authorizedIntent->refresh();
            if (
                $authorizedIntent->status !== 'requires_capture'
                || strtolower($authorizedIntent->currency) !== 'usd'
                || (int) $authorizedIntent->amount_cents < ($dispatchFeeCents + (int) config('work_orders.minimum_service_authorization_cents', 9500))
                || ! hash_equals(
                    (string) ($authorizedIntent->metadata['authorization_token_hash'] ?? ''),
                    hash('sha256', (string) $validated['payment_authorization_token']),
                )
                || (string) ($authorizedIntent->metadata['service_type'] ?? '') !== (string) $validated['service_type']
            ) {
                return response()->json(['error' => 'Payment authorization is not sufficient for dispatch.'], 422);
            }
        }

        $customer = null;
        if ($token = $request->bearerToken()) {
            $accessToken = PersonalAccessToken::findToken($token);
            $candidate = $accessToken?->tokenable;
            if ($candidate?->isCustomer()) {
                $customer = $candidate;
            }
        }

        if (! empty($validated['google_place_id']) && empty($validated['place_source'])) {
            $validated['place_source'] = 'google';
        }

        $preferredCompanyId = $this->resolvePreferredCompanyId(
            $validated['preferred_company_slug'] ?? null,
            $validated['provider_id'] ?? null,
        );
        unset($validated['preferred_company_slug'], $validated['provider_id']);

        $paymentAuthorizationToken = (string) ($validated['payment_authorization_token'] ?? '');
        unset($validated['payment_authorization_token']);

        try {
            $lead = DB::transaction(function () use ($validated, $customer, $preferredCompanyId, $request, $dispatchFeeCents, $paymentAuthorizationToken) {
                $paymentIntent = null;
                if (config('work_orders.enabled')) {
                    $paymentIntent = PaymentIntent::query()->lockForUpdate()->find($validated['payment_intent_id']);
                    if (
                        ! $paymentIntent
                        || $paymentIntent->lead_id
                        || $paymentIntent->status !== 'requires_capture'
                        || ! hash_equals(
                            (string) ($paymentIntent->metadata['authorization_token_hash'] ?? ''),
                            hash('sha256', $paymentAuthorizationToken),
                        )
                    ) {
                        throw new RuntimeException('Payment authorization has already been used or is no longer valid.');
                    }
                }

                $lead = Lead::create([
                    ...$validated,
                    'user_id' => $customer?->id,
                    'preferred_company_id' => $preferredCompanyId,
                    'work_order_number' => $this->nextWorkOrderNumber(),
                    'customer_name' => $validated['customer_name'] ?? $customer?->name,
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? $customer?->email,
                    'status' => 'new',
                    'customer_token' => Str::random(48),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'source' => $request->header('Referer', 'direct'),
                    'authorization_confirmed' => $request->boolean('authorization_confirmed'),
                    'authorization_confirmed_at' => $request->boolean('authorization_confirmed') ? now() : null,
                    'authorization_disclaimer_version' => $validated['authorization_disclaimer_version'] ?? 'customer_authorization_v1',
                    'dispatch_fee_cents' => $dispatchFeeCents,
                    'dispatch_fee_currency' => 'usd',
                    'dispatch_fee_policy_version' => $validated['dispatch_fee_policy_version'] ?? 'dispatch_fee_v1',
                    'dispatch_fee_acknowledged' => $request->boolean('dispatch_fee_acknowledged'),
                    'dispatch_fee_acknowledged_at' => $request->boolean('dispatch_fee_acknowledged') ? now() : null,
                ]);

                if ($paymentIntent) {
                    $booking = Booking::create([
                        'lead_id' => $lead->id,
                        'public_id' => 'LK-'.strtoupper(Str::random(8)),
                        'status' => BookingState::Searching->value,
                        'estimated_min_amount' => $paymentIntent->metadata['estimated_min'] ?? null,
                        'estimated_max_amount' => $paymentIntent->metadata['estimated_max'] ?? null,
                        'currency' => $paymentIntent->currency,
                        'authorized_at' => $paymentIntent->authorized_at ?? now(),
                        'customer_timezone' => $paymentIntent->metadata['timezone'] ?? null,
                        'metadata' => [
                            'source' => 'astro_authorized_dispatch',
                            'payment_intent_id' => $paymentIntent->id,
                        ],
                    ]);
                    $paymentIntent->update(['lead_id' => $lead->id, 'booking_id' => $booking->id]);
                }

                return $lead;
            });
        } catch (RuntimeException $exception) {
            return response()->json(['error' => $exception->getMessage()], 409);
        }

        app(DomainEventRecorder::class)->record('WorkOrderCreated', $lead, [
            'lead_id' => $lead->id,
            'work_order_number' => $lead->work_order_number,
            'service_type' => $lead->service_type,
            'preferred_company_id' => $lead->preferred_company_id,
            'dispatch_fee_cents' => $lead->dispatch_fee_cents,
            'dispatch_fee_currency' => $lead->dispatch_fee_currency,
            'dispatch_fee_policy_version' => $lead->dispatch_fee_policy_version,
        ]);

        dispatch(function () use ($lead) {
            $dispatch = app(DispatchService::class);
            $dispatch->sendCustomerTrackLink($lead);
            $dispatch->dispatch($lead);
        })->afterResponse();

        return response()->json([
            'success' => true,
            'lead_id' => $lead->id,
            'work_order_number' => $lead->work_order_number,
            'customer_token' => $lead->customer_token,
            'track_url' => LockNearUrls::customerTrack($lead),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['data' => [], 'meta' => ['total' => 0]]);
        }

        $leads = $company->leads()
            ->with(['lead', 'company'])
            ->latest()
            ->paginate(25);

        return response()->json($leads);
    }

    public function stats(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['data' => []]);
        }

        $base = $company->leads();

        $total = (clone $base)->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $active = (clone $base)->whereIn('status', ['accepted', 'en_route', 'arrived'])->count();
        $thisMonth = (clone $base)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return response()->json([
            'data' => [
                'leads_this_month' => $thisMonth,
                'leads_total' => $total,
                'completed_total' => $completed,
                'active_jobs' => $active,
                'rating' => $company->rating,
                'review_count' => $company->review_count,
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $assignment = $company->leads()
            ->with(['lead', 'company'])
            ->where('lead_id', $id)
            ->firstOrFail();

        return response()->json($assignment);
    }

    public function stream(Request $request, Lead $lead)
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $headers = [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ];

        return response()->stream(function () use ($company, $lead) {
            @set_time_limit(0);

            $sendSnapshot = function () use ($company, $lead) {
                $assignment = $company->leads()
                    ->where('lead_id', $lead->id)
                    ->with(['lead', 'company'])
                    ->first();

                if (! $assignment) {
                    echo "event: error\n";
                    echo 'data: '.json_encode(['error' => 'not_found'])."\n\n";
                    @ob_flush();
                    @flush();

                    return false;
                }

                echo "event: update\n";
                echo 'data: '.json_encode($assignment)."\n\n";
                @ob_flush();
                @flush();

                return true;
            };

            if (! $sendSnapshot()) {
                return;
            }

            for ($i = 0; $i < 40; $i++) {
                if (connection_aborted()) {
                    break;
                }
                usleep(3000000); // 3s
                if (! $sendSnapshot()) {
                    break;
                }
            }
        }, 200, $headers);
    }

    public function accept(Request $request, Lead $lead): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['error' => 'No company'], 403);
        }

        try {
            $acceptance = app(LeadAcceptanceService::class);
            $assignment = $acceptance->accept($lead, $company);
        } catch (LeadBillingException $e) {
            $status = $e->errorCode === 'already_taken' ? 409 : 402;

            return response()->json([
                'error' => $e->getMessage(),
                'code' => $e->errorCode,
            ], $status);
        }

        dispatch(function () use ($lead, $company, $assignment) {
            app(LeadAcceptanceService::class)->notifyAfterAccept($lead, $company, $assignment);
        })->afterResponse();

        return response()->json([
            'success' => true,
            'lead_status' => 'assigned',
            'assignment_status' => $assignment->status,
            'lead_cost' => $assignment->lead_cost,
            'stripe_charge_id' => $assignment->stripe_charge_id,
        ]);
    }

    public function reject(Request $request, Lead $lead): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $assignment = LeadAssignment::firstOrCreate(
            ['lead_id' => $lead->id, 'company_id' => $company->id],
            ['lead_cost' => LeadPricing::forService($lead->service_type)],
        );

        if ($assignment->status !== 'pending') {
            return response()->json(['error' => 'Lead can no longer be passed.'], 409);
        }

        $assignment->update([
            'status' => 'rejected',
            'responded_at' => now(),
        ]);

        return response()->json(['success' => true, 'assignment_status' => 'rejected']);
    }

    public function markEnRoute(Request $request, Lead $lead): JsonResponse
    {
        return $this->updateAssignmentStatus($request, $lead, 'en_route');
    }

    public function markArrived(Request $request, Lead $lead): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);
        $result = $this->updateAssignmentStatus($request, $lead, 'arrived');
        if ($result->status() !== 200) {
            return $result;
        }

        $company = $request->user()->company;
        $assignment = $company->leads()->where('lead_id', $lead->id)->firstOrFail();
        $this->recordLocationEvent($lead, $assignment, $company->id, $validated, 'arrival');

        return $result;
    }

    public function markUnableToVerify(Request $request, Lead $lead): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $assignment = $company->leads()
            ->where('lead_id', $lead->id)
            ->first();

        if (! $assignment) {
            return response()->json(['error' => 'Assignment not found'], 404);
        }

        if ($assignment->status !== 'arrived') {
            return response()->json(['error' => 'Mark arrival before reporting a verification issue.'], 409);
        }

        $validated = $request->validate([
            'service_refusal_reason' => ['nullable', 'string', 'in:unable_to_verify_ownership,no_photo_id,no_registration,no_authorization,customer_no_show,safety_concern,other'],
        ]);

        $feeCapture = app(DispatchFeeService::class)->captureForVerificationFailure($lead, $assignment);

        $payload = array_merge(
            [
                'status' => 'unable_to_verify',
                'service_refusal_reason' => $validated['service_refusal_reason'] ?? 'unable_to_verify_ownership',
                'service_refused_at' => now(),
                'dispatch_fee_eligible' => (int) ($lead->dispatch_fee_cents ?? 0) > 0 && $lead->dispatch_fee_acknowledged,
                'dispatch_fee_capture_status' => $feeCapture['dispatch_fee_capture_status']
                    ?? ((int) ($lead->dispatch_fee_cents ?? 0) > 0 ? 'eligible' : null),
                'dispatch_fee_capture_amount_cents' => $feeCapture['dispatch_fee_capture_amount_cents']
                    ?? ((int) ($lead->dispatch_fee_cents ?? 0) ?: null),
            ],
            $this->verificationPayload($request),
        );

        $assignment->update($payload);

        $lead->update(['status' => 'verification_failed']);

        app(DomainEventRecorder::class)->record('ServiceVerificationFailed', $lead, [
            'lead_id' => $lead->id,
            'work_order_number' => $lead->work_order_number,
            'company_id' => $company->id,
            'assignment_id' => $assignment->id,
            'service_refusal_reason' => $payload['service_refusal_reason'],
            'dispatch_fee_eligible' => $payload['dispatch_fee_eligible'],
            'dispatch_fee_capture_status' => $payload['dispatch_fee_capture_status'],
            'dispatch_fee_capture_amount_cents' => $payload['dispatch_fee_capture_amount_cents'],
            'verification_checklist' => $payload['verification_checklist'] ?? $assignment->verification_checklist,
        ], ['company_id' => $company->id]);

        broadcast(new DispatchStatusChanged($lead, $company, 'unable_to_verify'));

        dispatch(function () use ($lead, $company, $payload) {
            app(DispatchService::class)->sendCustomerVerificationFailed($lead->fresh(), $company, $payload);
        })->afterResponse();

        return response()->json([
            'success' => true,
            'lead_status' => 'verification_failed',
            'assignment_status' => 'unable_to_verify',
            'dispatch_fee_eligible' => $payload['dispatch_fee_eligible'],
            'dispatch_fee_capture_status' => $payload['dispatch_fee_capture_status'],
            'dispatch_fee_capture_amount_cents' => $payload['dispatch_fee_capture_amount_cents'],
        ]);
    }

    public function complete(Request $request, Lead $lead): JsonResponse
    {
        if (! config('work_orders.enabled')) {
            return $this->completeLegacy($request, $lead);
        }

        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $companyAssignment = $company->leads()->where('lead_id', $lead->id)->first();
        if (! $companyAssignment || $companyAssignment->status !== 'in_progress') {
            return response()->json(['error' => 'Start the approved work order before completing it.'], 409);
        }

        $quote = WorkOrderQuote::where('lead_id', $lead->id)
            ->where('company_id', $company->id)
            ->where('status', 'approved')
            ->latest('version')
            ->first();
        if (! $quote) {
            return response()->json(['error' => 'Customer price approval is required.'], 409);
        }
        if (! WorkOrderSignature::where('lead_id', $lead->id)->where('work_order_quote_id', $quote->id)->exists()) {
            return response()->json(['error' => 'Customer signature is required before payment capture.'], 409);
        }

        $paymentCapture = null;
        $paymentIntent = PaymentIntent::where('lead_id', $lead->id)
            ->where('company_id', $company->id)
            ->whereIn('status', ['requires_capture', 'succeeded'])
            ->latest('id')
            ->first();

        if (! $paymentIntent) {
            return response()->json(['error' => 'Authorized customer payment was not found.'], 409);
        }

        if ((int) $quote->total_cents > (int) $paymentIntent->amount_cents || strtolower($quote->currency) !== strtolower($paymentIntent->currency)) {
            return response()->json(['error' => 'Approved total does not match the payment authorization.'], 409);
        }

        if ($paymentIntent->status === 'requires_capture') {
            try {
                $paymentCapture = app(PaymentEngine::class)->capture([
                    'payment_intent_id' => $paymentIntent->id,
                    'amount_to_capture_cents' => (int) $quote->total_cents,
                    'idempotency_key' => 'lead_complete_capture_'.$lead->id,
                ]);
            } catch (RuntimeException $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'code' => 'payment_capture_failed',
                ], 402);
            }
        } elseif ((int) $paymentIntent->captured_amount_cents !== (int) $quote->total_cents) {
            return response()->json(['error' => 'Captured amount does not match the approved total.'], 409);
        }

        $result = $this->updateAssignmentStatus($request, $lead, 'completed');
        if ($result->status() !== 200) {
            return $result;
        }

        $lead->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $booking = Booking::where('lead_id', $lead->id)->first();
        $booking?->update([
            'status' => BookingState::Completed->value,
            'final_amount' => $quote->total_cents / 100,
            'paid_at' => now(),
            'completed_at' => now(),
        ]);

        WorkOrderInvoice::firstOrCreate(
            ['lead_id' => $lead->id],
            [
                'work_order_quote_id' => $quote->id,
                'invoice_number' => 'INV-'.now()->format('Ymd').'-'.strtoupper(Str::random(8)),
                'dispatch_fee_cents' => $quote->dispatch_fee_cents,
                'service_fee_cents' => $quote->service_fee_cents,
                'total_cents' => $quote->total_cents,
                'currency' => $quote->currency,
                'payment_status' => 'paid',
                'issued_at' => now(),
                'snapshot' => [
                    'work_order_number' => $lead->work_order_number,
                    'customer' => $lead->customer_name,
                    'company' => $company->name,
                    'service_type' => $lead->service_type,
                    'vehicle' => array_filter([$lead->vehicle_year, $lead->vehicle_make, $lead->vehicle_model]),
                    'vin' => $lead->vin,
                    'license_plate' => $lead->license_plate,
                ],
            ],
        );

        LeadCompleted::dispatch($lead->fresh(), $company);

        return response()->json([
            'success' => true,
            'lead_status' => 'completed',
            'assignment_status' => 'completed',
            'payment_capture' => $paymentCapture,
        ]);
    }

    private function completeLegacy(Request $request, Lead $lead): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $paymentCapture = null;
        $paymentIntent = PaymentIntent::where('lead_id', $lead->id)
            ->where('company_id', $company->id)
            ->where('status', 'requires_capture')
            ->latest('id')
            ->first();
        if ($paymentIntent) {
            try {
                $paymentCapture = app(PaymentEngine::class)->capture([
                    'payment_intent_id' => $paymentIntent->id,
                    'idempotency_key' => 'lead_complete_capture_'.$lead->id,
                ]);
            } catch (RuntimeException $exception) {
                return response()->json(['error' => $exception->getMessage(), 'code' => 'payment_capture_failed'], 402);
            }
        }

        $result = $this->updateAssignmentStatus($request, $lead, 'completed');
        if ($result->status() !== 200) {
            return $result;
        }
        $lead->update(['status' => 'completed', 'completed_at' => now()]);
        LeadCompleted::dispatch($lead->fresh(), $company);

        return response()->json([
            'success' => true,
            'lead_status' => 'completed',
            'assignment_status' => 'completed',
            'payment_capture' => $paymentCapture,
        ]);
    }

    public function updateLocation(Request $request, Lead $lead): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        $assignment = $company->leads()
            ->where('lead_id', $lead->id)
            ->first();

        if (! $assignment) {
            return response()->json(['error' => 'Assignment not found'], 404);
        }
        if (! in_array($assignment->status, ['accepted', 'en_route', 'arrived', 'in_progress'], true)) {
            return response()->json(['error' => 'Location can only be updated for an active job.'], 409);
        }

        $assignment->update([
            'provider_latitude' => $validated['latitude'],
            'provider_longitude' => $validated['longitude'],
            'last_location_at' => now(),
        ]);

        $this->recordLocationEvent($lead, $assignment, $company->id, $validated, 'location_update');

        broadcast(new ProviderLocationUpdated($lead, $validated['latitude'], $validated['longitude']));

        return response()->json(['success' => true]);
    }

    // Admin / general lead detail (for Filament)
    public function adminShow(Lead $lead): JsonResponse
    {
        return response()->json($lead->load('assignments.company'));
    }

    private function updateAssignmentStatus(Request $request, Lead $lead, string $targetStatus): JsonResponse
    {
        $company = $request->user()->company;
        if (! $company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $assignment = $company->leads()
            ->where('lead_id', $lead->id)
            ->first();

        if (! $assignment) {
            return response()->json(['error' => 'Assignment not found'], 404);
        }

        $allowed = [
            'en_route' => ['accepted'],
            'arrived' => ['en_route'],
            'completed' => ['in_progress'],
        ];
        if (isset($allowed[$targetStatus]) && ! in_array($assignment->status, $allowed[$targetStatus], true)) {
            return response()->json([
                'error' => "Invalid status transition from {$assignment->status} to {$targetStatus}.",
            ], 409);
        }

        $timestamps = match ($targetStatus) {
            'en_route' => ['en_route_at' => now()],
            'arrived' => ['arrived_at' => now()],
            'completed' => ['completed_at' => now()],
            default => [],
        };

        $assignment->update(array_merge(
            ['status' => $targetStatus],
            $timestamps,
            $this->verificationPayload($request),
        ));

        broadcast(new DispatchStatusChanged($lead, $company, $targetStatus));

        return response()->json([
            'success' => true,
            'assignment_status' => $assignment->status,
        ]);
    }

    private function verificationPayload(Request $request): array
    {
        if (! $request->hasAny([
            'verification_checklist',
            'id_checked',
            'ownership_checked',
            'authorization_checked',
            'verification_notes',
        ])) {
            return [];
        }

        $validated = $request->validate([
            'verification_checklist' => ['nullable', 'array'],
            'verification_checklist.id_checked' => ['nullable', 'boolean'],
            'verification_checklist.ownership_checked' => ['nullable', 'boolean'],
            'verification_checklist.authorization_checked' => ['nullable', 'boolean'],
            'id_checked' => ['nullable', 'boolean'],
            'ownership_checked' => ['nullable', 'boolean'],
            'authorization_checked' => ['nullable', 'boolean'],
            'verification_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $checklist = $validated['verification_checklist'] ?? [];
        foreach (['id_checked', 'ownership_checked', 'authorization_checked'] as $key) {
            if ($request->has($key)) {
                $checklist[$key] = $request->boolean($key);
            }
        }

        $payload = [
            'verification_checklist' => $checklist,
            'verification_checked_at' => now(),
        ];

        if (array_key_exists('verification_notes', $validated)) {
            $payload['verification_notes'] = $validated['verification_notes'];
        }

        return $payload;
    }

    private function resolvePreferredCompanyId(?string $slug, ?string $providerId): ?int
    {
        $candidate = $slug ?: $providerId;
        if (! $candidate) {
            return null;
        }

        $query = Company::query()->where('is_active', true);

        if (ctype_digit($candidate)) {
            return $query->whereKey((int) $candidate)->value('id');
        }

        return $query->where('slug', $candidate)->value('id');
    }

    private function nextWorkOrderNumber(): string
    {
        do {
            $number = 'WO-'.strtoupper(Str::random(8));
        } while (Lead::where('work_order_number', $number)->exists());

        return $number;
    }

    private function recordLocationEvent(Lead $lead, LeadAssignment $assignment, int $companyId, array $coordinates, string $eventType): void
    {
        $assignment->update([
            'provider_latitude' => $coordinates['latitude'],
            'provider_longitude' => $coordinates['longitude'],
            'last_location_at' => now(),
        ]);

        \DB::table('work_order_location_events')->insert([
            'lead_id' => $lead->id,
            'lead_assignment_id' => $assignment->id,
            'company_id' => $companyId,
            'latitude' => $coordinates['latitude'],
            'longitude' => $coordinates['longitude'],
            'accuracy_meters' => $coordinates['accuracy_meters'] ?? null,
            'event_type' => $eventType,
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
