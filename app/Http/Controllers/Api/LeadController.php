<?php

namespace App\Http\Controllers\Api;

use App\Events\DispatchStatusChanged;
use App\Events\LeadCompleted;
use App\Events\ProviderLocationUpdated;
use App\Enums\BookingState;
use App\Exceptions\LeadBillingException;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\PaymentIntent;
use App\Services\DispatchService;
use App\Services\LeadAcceptanceService;
use App\Services\PaymentEngine;
use App\Support\LeadPricing;
use App\Support\LockNearUrls;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class LeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zip'               => ['required', 'string', 'size:5', 'regex:/^[0-9]+$/'],
            'service_type'      => ['required', 'string', 'in:car-lockout,car-key-replacement,house-lockout,lock-rekey,commercial,emergency,24-hour-locksmith,emergency-locksmith,locked-keys-in-car,lost-car-keys,key-fob-programming,ignition-repair'],
            'phone'             => ['required_without:email', 'nullable', 'string', 'min:10'],
            'email'             => ['required_without:phone', 'nullable', 'email', 'max:255'],
            'description'       => ['nullable', 'string', 'max:1000'],
            'customer_name'     => ['nullable', 'string', 'max:100'],
            'latitude'          => ['nullable', 'numeric'],
            'longitude'         => ['nullable', 'numeric'],
            'city'              => ['nullable', 'string', 'max:100'],
            'state'             => ['nullable', 'string', 'max:2'],
            'google_place_id'   => ['nullable', 'string', 'max:255'],
            'formatted_address' => ['nullable', 'string', 'max:500'],
            'address_components'=> ['nullable', 'array'],
            'place_source'      => ['nullable', 'string', 'in:google,manual,gps'],
            'payment_intent_id' => ['nullable', 'integer', 'exists:payment_intents,id'],
        ]);

        $customer = null;
        if ($token = $request->bearerToken()) {
            $accessToken = PersonalAccessToken::findToken($token);
            $candidate = $accessToken?->tokenable;
            if ($candidate?->isCustomer()) {
                $customer = $candidate;
            }
        }

        if (!empty($validated['google_place_id']) && empty($validated['place_source'])) {
            $validated['place_source'] = 'google';
        }

        $lead = Lead::create([
            ...$validated,
            'user_id'        => $customer?->id,
            'customer_name'  => $validated['customer_name'] ?? $customer?->name,
            'email'          => $validated['email'] ?? $customer?->email,
            'status'         => 'new',
            'customer_token' => Str::random(48),
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'source'         => $request->header('Referer', 'direct'),
        ]);

        if (!empty($validated['payment_intent_id'])) {
            $paymentIntent = PaymentIntent::whereKey($validated['payment_intent_id'])
                ->whereNull('lead_id')
                ->first();

            if ($paymentIntent) {
                $booking = Booking::firstOrCreate(
                    ['lead_id' => $lead->id],
                    [
                        'public_id' => 'LK-' . strtoupper(Str::random(8)),
                        'status' => $paymentIntent->authorized_at
                            ? BookingState::Searching->value
                            : BookingState::Pending->value,
                        'estimated_min_amount' => $paymentIntent->metadata['estimated_min'] ?? null,
                        'estimated_max_amount' => $paymentIntent->metadata['estimated_max'] ?? null,
                        'currency' => $paymentIntent->currency ?? config('locknear.pricing.default_currency', 'usd'),
                        'authorized_at' => $paymentIntent->authorized_at,
                        'customer_timezone' => $paymentIntent->metadata['timezone'] ?? null,
                        'metadata' => [
                            'source' => 'astro_authorized_dispatch',
                            'payment_intent_id' => $paymentIntent->id,
                        ],
                    ],
                );

                $paymentIntent->update([
                    'lead_id' => $lead->id,
                    'booking_id' => $booking->id,
                ]);
            }
        }

        dispatch(function () use ($lead) {
            $dispatch = app(DispatchService::class);
            $dispatch->sendCustomerTrackLink($lead);
            $dispatch->dispatch($lead);
        })->afterResponse();

        return response()->json([
            'success'        => true,
            'lead_id'        => $lead->id,
            'customer_token' => $lead->customer_token,
            'track_url'      => LockNearUrls::customerTrack($lead),
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
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
        if (!$company) {
            return response()->json(['data' => []]);
        }

        $base = $company->leads();

        $total    = (clone $base)->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $active   = (clone $base)->whereIn('status', ['accepted', 'en_route', 'arrived'])->count();
        $thisMonth = (clone $base)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return response()->json([
            'data' => [
                'leads_this_month' => $thisMonth,
                'leads_total'      => $total,
                'completed_total'  => $completed,
                'active_jobs'      => $active,
                'rating'           => $company->rating,
                'review_count'     => $company->review_count,
            ],
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
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
        if (!$company) {
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

                if (!$assignment) {
                    echo "event: error\n";
                    echo 'data: ' . json_encode(['error' => 'not_found']) . "\n\n";
                    @ob_flush();
                    @flush();
                    return false;
                }

                echo "event: update\n";
                echo 'data: ' . json_encode($assignment) . "\n\n";
                @ob_flush();
                @flush();
                return true;
            };

            if (!$sendSnapshot()) {
                return;
            }

            for ($i = 0; $i < 40; $i++) {
                if (connection_aborted()) {
                    break;
                }
                usleep(3000000); // 3s
                if (!$sendSnapshot()) {
                    break;
                }
            }
        }, 200, $headers);
    }

    public function accept(Request $request, Lead $lead): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
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
        if (!$company) {
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
        return $this->updateAssignmentStatus($request, $lead, 'arrived');
    }

    public function complete(Request $request, Lead $lead): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
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
                    'idempotency_key' => 'lead_complete_capture_' . $lead->id,
                ]);
            } catch (RuntimeException $e) {
                return response()->json([
                    'error' => $e->getMessage(),
                    'code' => 'payment_capture_failed',
                ], 402);
            }
        }

        $result = $this->updateAssignmentStatus($request, $lead, 'completed');
        if ($result->status() !== 200) {
            return $result;
        }

        $lead->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

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
        if (!$company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $assignment = $company->leads()
            ->where('lead_id', $lead->id)
            ->first();

        if (!$assignment) {
            return response()->json(['error' => 'Assignment not found'], 404);
        }

        $assignment->update([
            'provider_latitude' => $validated['latitude'],
            'provider_longitude' => $validated['longitude'],
            'last_location_at' => now(),
        ]);

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
        if (!$company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $assignment = $company->leads()
            ->where('lead_id', $lead->id)
            ->first();

        if (!$assignment) {
            return response()->json(['error' => 'Assignment not found'], 404);
        }

        $timestamps = match ($targetStatus) {
            'en_route' => ['en_route_at' => now()],
            'arrived' => ['arrived_at' => now()],
            'completed' => ['completed_at' => now()],
            default => [],
        };

        $assignment->update(array_merge(['status' => $targetStatus], $timestamps));

        broadcast(new DispatchStatusChanged($lead, $company, $targetStatus));

        return response()->json([
            'success' => true,
            'assignment_status' => $assignment->status,
        ]);
    }
}
