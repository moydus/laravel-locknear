<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\PaymentIntent;
use App\Models\WorkOrderDispute;
use App\Models\WorkOrderEvidence;
use App\Models\WorkOrderInvoice;
use App\Models\WorkOrderQuote;
use App\Models\WorkOrderSignature;
use App\Services\PaymentEngine;
use App\Support\TrackPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WorkOrderController extends Controller
{
    public function providerShow(Request $request, Lead $lead): JsonResponse
    {
        $this->ensureEnabled();
        [$company, $assignment] = $this->providerAssignment($request, $lead);

        return response()->json(['data' => $this->summary($lead, $assignment->id, true)]);
    }

    public function proposeQuote(Request $request, Lead $lead): JsonResponse
    {
        $this->ensureEnabled();
        [$company, $assignment] = $this->providerAssignment($request, $lead);
        abort_unless($assignment->status === 'arrived', 409, 'A quote can only be proposed after arrival and before work starts.');

        $validated = $request->validate([
            'service_fee_cents' => ['required', 'integer', 'min:0', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $dispatchFee = (int) $lead->dispatch_fee_cents;
        $total = $dispatchFee + (int) $validated['service_fee_cents'];
        $intent = PaymentIntent::where('lead_id', $lead->id)->where('status', 'requires_capture')->latest('id')->first();
        abort_unless($intent && $total <= (int) $intent->amount_cents, 422, 'Quote exceeds the customer-authorized amount.');

        $quote = DB::transaction(function () use ($lead, $company, $assignment, $validated, $dispatchFee, $total) {
            WorkOrderQuote::where('lead_id', $lead->id)->where('status', 'pending')->update(['status' => 'superseded']);
            $version = ((int) WorkOrderQuote::where('lead_id', $lead->id)->max('version')) + 1;

            return WorkOrderQuote::create([
                'lead_id' => $lead->id,
                'company_id' => $company->id,
                'lead_assignment_id' => $assignment->id,
                'dispatch_fee_cents' => $dispatchFee,
                'service_fee_cents' => $validated['service_fee_cents'],
                'total_cents' => $total,
                'currency' => $lead->dispatch_fee_currency,
                'description' => $validated['description'] ?? null,
                'status' => 'pending',
                'version' => $version,
                'proposed_at' => now(),
            ]);
        });

        $this->recordStatusEvent($lead, $company->id, $lead->status, 'quote_pending', 'provider', $request->user()->id, ['quote_id' => $quote->id]);

        return response()->json(['data' => $quote], 201);
    }

    public function uploadEvidence(Request $request, Lead $lead): JsonResponse
    {
        $this->ensureEnabled();
        [$company, $assignment] = $this->providerAssignment($request, $lead);
        abort_unless(in_array($assignment->status, ['arrived', 'in_progress'], true), 409, 'Evidence can only be uploaded after arrival.');

        $validated = $request->validate([
            'type' => ['required', Rule::in(['photo_id', 'registration', 'vin_label', 'vehicle', 'before_work', 'after_work', 'other'])],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $file = $validated['file'];
        $bytes = file_get_contents($file->getRealPath());
        $path = $file->storeAs(
            'work-orders/'.$lead->id,
            Str::uuid().'.'.$file->guessExtension(),
            'local',
        );

        $evidence = WorkOrderEvidence::create([
            'lead_id' => $lead->id,
            'lead_assignment_id' => $assignment->id,
            'company_id' => $company->id,
            'type' => $validated['type'],
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'sha256' => hash('sha256', $bytes),
            'expires_at' => now()->addDays((int) config('work_orders.evidence_retention_days', 30)),
        ]);

        return response()->json(['data' => $evidence->only(['id', 'type', 'mime_type', 'size_bytes', 'expires_at'])], 201);
    }

    public function downloadEvidence(Request $request, Lead $lead, WorkOrderEvidence $evidence)
    {
        $this->ensureEnabled();
        $this->providerAssignment($request, $lead);
        abort_unless($evidence->lead_id === $lead->id && $evidence->status === 'active', 404);

        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name);
    }

    public function start(Request $request, Lead $lead): JsonResponse
    {
        $this->ensureEnabled();
        [$company, $assignment] = $this->providerAssignment($request, $lead);
        abort_unless($assignment->status === 'arrived', 409, 'The job must be in arrived state.');
        $quote = WorkOrderQuote::where('lead_id', $lead->id)->latest('version')->first();
        abort_unless($quote?->status === 'approved' && (int) $quote->company_id === (int) $company->id, 409, 'Customer approval of the latest price is required.');

        $assignment->update(['status' => 'in_progress']);
        $from = $lead->status;
        $lead->update(['status' => 'in_progress']);
        $this->recordStatusEvent($lead, $company->id, $from, 'in_progress', 'provider', $request->user()->id);

        return response()->json(['success' => true, 'assignment_status' => 'in_progress']);
    }

    public function customerShow(string $token): JsonResponse
    {
        $this->ensureEnabled();
        $lead = $this->leadByToken($token);
        $assignment = TrackPayload::resolveActiveAssignment($lead);

        return response()->json(['data' => $this->summary($lead, $assignment?->id, false)]);
    }

    public function approveQuote(Request $request, string $token, WorkOrderQuote $quote): JsonResponse
    {
        $this->ensureEnabled();
        $lead = $this->leadByToken($token);
        abort_unless($quote->lead_id === $lead->id && $quote->status === 'pending', 409, 'Quote is no longer pending.');
        abort_unless((int) WorkOrderQuote::where('lead_id', $lead->id)->max('version') === (int) $quote->version, 409, 'Only the latest quote can be approved.');
        $assignment = TrackPayload::resolveActiveAssignment($lead);
        abort_unless($assignment && $assignment->status === 'arrived' && (int) $assignment->company_id === (int) $quote->company_id, 409, 'The provider must be at the service location.');
        $intent = PaymentIntent::where('lead_id', $lead->id)->where('status', 'requires_capture')->latest('id')->first();
        abort_unless($intent && (int) $quote->total_cents <= (int) $intent->amount_cents && strtolower($quote->currency) === strtolower($intent->currency), 409, 'The quote is not covered by the payment authorization.');

        DB::transaction(function () use ($quote, $lead, $request) {
            WorkOrderQuote::where('lead_id', $lead->id)
                ->where('id', '!=', $quote->id)
                ->whereIn('status', ['pending', 'approved'])
                ->update(['status' => 'superseded']);
            $quote->update(['status' => 'approved', 'approved_at' => now(), 'approved_ip' => $request->ip()]);
        });
        $this->recordStatusEvent($lead, $quote->company_id, $lead->status, 'quote_approved', 'customer', $lead->user_id, ['quote_id' => $quote->id]);

        return response()->json(['success' => true, 'data' => $quote->fresh()]);
    }

    public function rejectQuote(Request $request, string $token, WorkOrderQuote $quote): JsonResponse
    {
        $this->ensureEnabled();
        $lead = $this->leadByToken($token);
        abort_unless($quote->lead_id === $lead->id && $quote->status === 'pending', 409);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $quote->update(['status' => 'rejected', 'rejected_at' => now(), 'rejection_reason' => $validated['reason'] ?? null]);

        return response()->json(['success' => true]);
    }

    public function sign(Request $request, string $token): JsonResponse
    {
        $this->ensureEnabled();
        $lead = $this->leadByToken($token);
        abort_unless($lead->status === 'in_progress', 409, 'The approved work must be in progress before it can be signed.');
        abort_if(WorkOrderSignature::where('lead_id', $lead->id)->exists(), 409, 'Work order is already signed.');
        $quote = WorkOrderQuote::where('lead_id', $lead->id)->latest('version')->firstOrFail();
        abort_unless($quote->status === 'approved', 409, 'The latest quote is not approved.');

        $validated = $request->validate([
            'signer_name' => ['required', 'string', 'max:100'],
            'signature' => ['required', 'string', 'max:750000'],
            'consent_version' => ['required', 'string', 'max:64'],
        ]);
        abort_unless(preg_match('/^data:image\/png;base64,(.+)$/', $validated['signature'], $matches), 422, 'Signature must be a PNG data URL.');
        $bytes = base64_decode($matches[1], true);
        abort_unless($bytes !== false && strlen($bytes) <= 500000 && str_starts_with($bytes, "\x89PNG\r\n\x1a\n"), 422, 'Invalid signature data.');

        $path = 'work-orders/'.$lead->id.'/signature-'.Str::uuid().'.png';
        Storage::disk('local')->put($path, $bytes);
        $signature = WorkOrderSignature::create([
            'lead_id' => $lead->id,
            'work_order_quote_id' => $quote->id,
            'signer_name' => $validated['signer_name'],
            'disk' => 'local',
            'path' => $path,
            'sha256' => hash('sha256', $bytes),
            'consent_version' => $validated['consent_version'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'signed_at' => now(),
        ]);

        return response()->json(['success' => true, 'signed_at' => $signature->signed_at]);
    }

    public function cancel(Request $request, string $token): JsonResponse
    {
        $this->ensureEnabled();
        $lead = $this->leadByToken($token);
        $assignment = TrackPayload::resolveActiveAssignment($lead);
        abort_if($assignment && in_array($assignment->status, ['arrived', 'in_progress', 'completed'], true), 409, 'Contact support to cancel after arrival.');
        $validated = $request->validate(['reason' => ['required', Rule::in(['provider_no_show', 'no_longer_needed', 'duplicate', 'other'])]]);

        $intent = PaymentIntent::where('lead_id', $lead->id)->where('status', 'requires_capture')->latest('id')->first();
        if ($intent) {
            app(PaymentEngine::class)->cancel(['payment_intent_id' => $intent->id, 'idempotency_key' => 'customer_cancel_'.$lead->id]);
        }
        $from = $lead->status;
        $lead->update(['status' => 'cancelled', 'customer_cancelled_at' => now(), 'customer_cancellation_reason' => $validated['reason']]);
        $assignment?->update(['status' => 'cancelled']);
        $this->recordStatusEvent($lead, $assignment?->company_id, $from, 'cancelled', 'customer', $lead->user_id, ['reason' => $validated['reason']]);

        return response()->json(['success' => true, 'hold_released' => (bool) $intent]);
    }

    public function dispute(Request $request, string $token): JsonResponse
    {
        $this->ensureEnabled();
        $lead = $this->leadByToken($token);
        abort_unless(in_array($lead->status, ['completed', 'verification_failed'], true), 409, 'A dispute can be opened after the work order closes.');
        abort_if(WorkOrderDispute::where('lead_id', $lead->id)->whereIn('status', ['open', 'under_review'])->exists(), 409, 'An active dispute already exists.');
        $validated = $request->validate([
            'reason' => ['required', Rule::in(['service_not_performed', 'price', 'damage', 'dispatch_fee', 'other'])],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
        ]);
        $dispute = WorkOrderDispute::create([
            'lead_id' => $lead->id,
            'user_id' => $lead->user_id,
            'public_id' => 'DSP-'.strtoupper(Str::random(10)),
            ...$validated,
        ]);

        return response()->json(['data' => $dispute], 201);
    }

    public function invoice(string $token): JsonResponse
    {
        $this->ensureEnabled();
        $lead = $this->leadByToken($token);
        $invoice = WorkOrderInvoice::where('lead_id', $lead->id)->firstOrFail();

        return response()->json(['data' => $invoice]);
    }

    private function providerAssignment(Request $request, Lead $lead): array
    {
        $company = $request->user()?->company;
        abort_unless($company, 403, 'No company.');
        $assignment = $company->leads()->where('lead_id', $lead->id)->first();
        abort_unless($assignment, 404, 'Assignment not found.');

        return [$company, $assignment];
    }

    private function leadByToken(string $token): Lead
    {
        return Lead::where('customer_token', $token)->firstOrFail();
    }

    private function summary(Lead $lead, ?int $assignmentId, bool $provider): array
    {
        $assignment = $assignmentId ? LeadAssignment::find($assignmentId) : null;
        $quote = WorkOrderQuote::where('lead_id', $lead->id)->latest('version')->first();
        $signature = WorkOrderSignature::where('lead_id', $lead->id)->first();
        $invoice = WorkOrderInvoice::where('lead_id', $lead->id)->first();
        $dispute = WorkOrderDispute::where('lead_id', $lead->id)->latest()->first();
        $evidence = WorkOrderEvidence::where('lead_id', $lead->id)->where('status', 'active')->get();
        $locationEvents = $provider
            ? DB::table('work_order_location_events')->where('lead_id', $lead->id)->latest('recorded_at')->limit(100)->get()
            : collect();

        return [
            'work_order_number' => $lead->work_order_number,
            'status' => $assignment?->status ?? $lead->status,
            'lead_status' => $lead->status,
            'service_type' => $lead->service_type,
            'vehicle' => array_filter(['year' => $lead->vehicle_year, 'make' => $lead->vehicle_make, 'model' => $lead->vehicle_model, 'color' => $lead->vehicle_color, 'vin' => $lead->vin, 'license_plate' => $lead->license_plate]),
            'pre_verification' => [
                'owned_or_authorized' => $lead->vehicle_owned_or_authorized,
                'registration_available' => $lead->registration_available,
                'photo_id_available' => $lead->photo_id_available,
                'document_names_match' => $lead->document_names_match,
            ],
            'dispatch_fee_cents' => $lead->dispatch_fee_cents,
            'quote' => $quote,
            'signature' => $signature?->only(['signer_name', 'consent_version', 'signed_at']),
            'invoice' => $invoice,
            'dispute' => $dispute,
            'evidence' => $provider
                ? $evidence->map->only(['id', 'type', 'mime_type', 'size_bytes', 'expires_at'])
                : ['count' => $evidence->count(), 'types' => $evidence->pluck('type')->unique()->values()],
            'assignment_id' => $assignmentId,
            'location_events' => $provider ? $locationEvents : null,
        ];
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('work_orders.enabled'), 404);
    }

    private function recordStatusEvent(Lead $lead, ?int $companyId, ?string $from, string $to, string $actorType, ?int $actorId, array $metadata = []): void
    {
        DB::table('work_order_status_events')->insert([
            'lead_id' => $lead->id,
            'company_id' => $companyId,
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'metadata' => json_encode($metadata),
            'recorded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
