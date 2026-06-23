<?php

namespace App\Http\Controllers\Api;

use App\Events\DispatchStatusChanged;
use App\Exceptions\LeadBillingException;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadToken;
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

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);

        \App\Models\Review::create([
            'lead_id'       => $leadToken->lead_id,
            'company_id'    => $leadToken->company_id,
            'rating'        => $validated['rating'],
            'body'          => $validated['comment'] ?? null,
            'is_verified'   => true,
            'is_published'  => true,
        ]);

        $company = $leadToken->company;
        $avg = \App\Models\Review::where('company_id', $company->id)
            ->where('is_published', true)
            ->avg('rating');
        $count = \App\Models\Review::where('company_id', $company->id)
            ->where('is_published', true)
            ->count();
        $company->update(['rating' => round($avg, 2), 'review_count' => $count]);

        $leadToken->markUsed();

        return response()->view('dispatch.review-submitted');
    }
}
