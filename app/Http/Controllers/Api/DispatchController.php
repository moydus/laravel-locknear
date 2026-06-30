<?php

namespace App\Http\Controllers\Api;

use App\Events\DispatchStatusChanged;
use App\Exceptions\LeadBillingException;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadAssignment;
use App\Models\LeadToken;
use App\Support\LeadPricing;
use App\Services\LeadAcceptanceService;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function accept(string $token)
    {
        $leadToken = LeadToken::where('token', $token)
            ->where('type', 'accept')
            ->first();

        if (!$leadToken || !$leadToken->isValid()) {
            return response()->view('dispatch.expired', [], 410);
        }

        $lead = $leadToken->lead;
        $company = $leadToken->company;

        if ($lead->status !== 'new' && $lead->status !== 'assigned') {
            return response()->view('dispatch.already-taken', ['lead' => $lead], 409);
        }

        try {
            $acceptance = app(LeadAcceptanceService::class);
            $assignment = $acceptance->accept($lead, $company);
        } catch (LeadBillingException $e) {
            if ($e->errorCode === 'already_taken') {
                return response()->view('dispatch.already-taken', ['lead' => $lead], 409);
            }

            return response()->view('dispatch.payment-required', [
                'lead' => $lead,
                'company' => $company,
                'message' => $e->getMessage(),
                'code' => $e->errorCode,
            ], 402);
        }

        $leadToken->markUsed();

        $acceptance->notifyAfterAccept($lead, $company, $assignment);

        return response()->view('dispatch.accepted', [
            'lead' => $lead->fresh(),
            'company' => $company,
            'assignment' => $assignment,
        ]);
    }

    public function reject(string $token)
    {
        $leadToken = LeadToken::where('token', $token)
            ->where('type', 'reject')
            ->first();

        if (!$leadToken || !$leadToken->isValid()) {
            return response()->view('dispatch.expired', [], 410);
        }

        $lead = $leadToken->lead;
        $company = $leadToken->company;

        if ($lead && $company) {
            $assignment = LeadAssignment::firstOrCreate(
                ['lead_id' => $lead->id, 'company_id' => $company->id],
                [
                    'status' => 'pending',
                    'lead_cost' => LeadPricing::forService($lead->service_type),
                ],
            );

            if ($assignment->status === 'pending') {
                $assignment->update([
                    'status' => 'rejected',
                    'responded_at' => now(),
                ]);
            }
        }

        $leadToken->markUsed();

        return response()->view('dispatch.rejected');
    }

    public function review(string $token)
    {
        $leadToken = LeadToken::where('token', $token)
            ->where('type', 'review')
            ->with(['lead', 'company'])
            ->first();

        if (!$leadToken || !$leadToken->isValid()) {
            return response()->view('dispatch.expired', [], 410);
        }

        if ($leadToken->lead?->status !== 'completed') {
            return response()->view('dispatch.expired', [], 410);
        }

        return response()->view('dispatch.review', [
            'token' => $token,
            'lead' => $leadToken->lead,
            'company' => $leadToken->company,
        ]);
    }

    public function submitReview(Request $request, string $token)
    {
        $leadToken = LeadToken::where('token', $token)
            ->where('type', 'review')
            ->with(['lead', 'company'])
            ->first();

        if (!$leadToken || !$leadToken->isValid()) {
            return response()->view('dispatch.expired', [], 410);
        }

        if ($leadToken->lead?->status !== 'completed') {
            return response()->view('dispatch.expired', [], 410);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        \App\Models\Review::create([
            'lead_id'       => $leadToken->lead_id,
            'company_id'    => $leadToken->company_id,
            'reviewer_name' => $leadToken->lead?->customer_name ?: 'LockNear customer',
            'reviewer_email'=> $leadToken->lead?->email,
            'rating'        => $validated['rating'],
            'body'          => $validated['comment'] ?? null,
            'is_verified'   => true,
            'is_published'  => true,
        ]);

        $leadToken->markUsed();

        return response()->view('dispatch.review-submitted');
    }
}
