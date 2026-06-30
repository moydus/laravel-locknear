<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingState;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Lead;
use App\Models\PaymentIntent;
use App\Services\PaymentEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentIntentController extends Controller
{
    public function __construct(private PaymentEngine $payments) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'amount_cents' => ['required', 'integer', 'min:50', 'max:99999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'purpose' => ['nullable', 'string', 'max:80'],
            'receipt_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $booking = $this->resolveBooking($validated);

        try {
            $intent = $this->payments->createIntent([
                ...$validated,
                'booking_id' => $booking?->id ?? $validated['booking_id'] ?? null,
                'lead_id' => $booking?->lead_id ?? $validated['lead_id'] ?? null,
                'company_id' => $booking?->company_id ?? $validated['company_id'] ?? null,
                'purpose' => $validated['purpose'] ?? 'service_authorization',
                'idempotency_key' => $validated['idempotency_key'] ?? $request->header('Idempotency-Key'),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if ($booking) {
            $booking->update([
                'status' => BookingState::Pending->value,
                'authorized_at' => null,
            ]);
        }

        return response()->json(['data' => $intent], 201);
    }

    public function authorize(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        $this->authorizePaymentAccess($request, $paymentIntent);

        $validated = $request->validate([
            'payment_method' => ['nullable', 'string', 'max:255'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $intent = $this->payments->authorize([
                'payment_intent_id' => $paymentIntent->id,
                ...$validated,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        if ($intent['requires_capture'] ?? false) {
            $paymentIntent->booking?->update([
                'authorized_at' => now(),
                'status' => BookingState::Searching->value,
            ]);
        }

        return response()->json(['data' => $intent]);
    }

    public function capture(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        $this->authorizePaymentAccess($request, $paymentIntent);

        $validated = $request->validate([
            'amount_to_capture_cents' => ['nullable', 'integer', 'min:50', 'max:99999999'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $intent = $this->payments->capture([
                'payment_intent_id' => $paymentIntent->id,
                ...$validated,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $paymentIntent->booking?->update([
            'paid_at' => now(),
            'final_amount' => ((int) $intent['captured_amount_cents']) / 100,
            'status' => BookingState::Completed->value,
        ]);

        return response()->json(['data' => $intent]);
    }

    public function cancel(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        $this->authorizePaymentAccess($request, $paymentIntent);

        $validated = $request->validate([
            'cancellation_reason' => ['nullable', 'string', 'in:duplicate,fraudulent,requested_by_customer,abandoned'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $intent = $this->payments->cancel([
                'payment_intent_id' => $paymentIntent->id,
                ...$validated,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $paymentIntent->booking?->update([
            'cancelled_at' => now(),
            'status' => BookingState::Cancelled->value,
        ]);

        return response()->json(['data' => $intent]);
    }

    public function cancelPublic(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        try {
            $this->payments->authorize([
                'payment_intent_id' => $paymentIntent->id,
                'idempotency_key' => $request->header('Idempotency-Key'),
            ]);
            $paymentIntent->refresh();
        } catch (\Throwable $e) {
            report($e);
        }

        if (in_array($paymentIntent->status, ['canceled', 'succeeded'], true)) {
            return response()->json(['data' => ['status' => $paymentIntent->status]]);
        }

        if (!in_array($paymentIntent->status, ['requires_capture', 'requires_confirmation', 'requires_payment_method'], true)) {
            return response()->json(['error' => 'Payment intent cannot be cancelled.'], 422);
        }

        try {
            $intent = $this->payments->cancel([
                'payment_intent_id' => $paymentIntent->id,
                'cancellation_reason' => $request->input('cancellation_reason', 'abandoned'),
                'idempotency_key' => $request->header('Idempotency-Key'),
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['error' => 'Unable to cancel payment hold.'], 422);
        }

        return response()->json(['data' => $intent]);
    }

    public function authorizePublic(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        try {
            $intent = $this->payments->authorize([
                'payment_intent_id' => $paymentIntent->id,
                'idempotency_key' => $request->header('Idempotency-Key'),
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $intent]);
    }

    public function refund(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        $this->authorizePaymentAccess($request, $paymentIntent);

        $validated = $request->validate([
            'amount_cents' => ['nullable', 'integer', 'min:50', 'max:99999999'],
            'reason' => ['nullable', 'string', 'in:duplicate,fraudulent,requested_by_customer'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $refund = $this->payments->refund([
                'payment_intent_id' => $paymentIntent->id,
                ...$validated,
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        $paymentIntent->booking?->update(['status' => BookingState::Refunded->value]);

        return response()->json(['data' => $refund], 201);
    }

    private function resolveBooking(array $payload): ?Booking
    {
        if (!empty($payload['booking_id'])) {
            return Booking::find($payload['booking_id']);
        }

        if (empty($payload['lead_id'])) {
            return null;
        }

        $lead = Lead::find($payload['lead_id']);
        if (!$lead) {
            return null;
        }

        return Booking::firstOrCreate(
            ['lead_id' => $lead->id],
            [
                'public_id' => 'LK-' . strtoupper(Str::random(8)),
                'status' => BookingState::Pending->value,
                'estimated_min_amount' => $payload['metadata']['estimated_min'] ?? null,
                'estimated_max_amount' => $payload['metadata']['estimated_max'] ?? null,
                'currency' => strtolower($payload['currency'] ?? config('locknear.pricing.default_currency', 'usd')),
                'customer_timezone' => $payload['metadata']['timezone'] ?? null,
                'metadata' => ['source' => 'payment_intent'],
            ],
        );
    }

    private function authorizePaymentAccess(Request $request, PaymentIntent $paymentIntent): void
    {
        $company = $request->user()?->company;
        abort_unless($company, 403, 'No company found.');

        if ($paymentIntent->company_id) {
            abort_unless((int) $paymentIntent->company_id === (int) $company->id, 403);
        }
    }
}
