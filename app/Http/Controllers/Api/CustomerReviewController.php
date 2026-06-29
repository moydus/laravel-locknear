<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerReviewController extends Controller
{
    public function store(Request $request, Lead $lead): JsonResponse
    {
        $user = $request->user();

        if (!$user?->isCustomer()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!$this->canReviewLead($lead, $user)) {
            return response()->json([
                'message' => 'You can review only your completed LockNear jobs.',
            ], 403);
        }

        if ($lead->reviews()->where('reviewer_email', $user->email)->exists()) {
            return response()->json([
                'message' => 'You have already reviewed this job.',
            ], 409);
        }

        $assignment = $lead->assignments()
            ->where('status', 'completed')
            ->latest('completed_at')
            ->first();

        if (!$assignment) {
            return response()->json([
                'message' => 'A completed provider assignment is required before review.',
            ], 422);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'speed_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'communication_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'professionalism_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'price_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'body' => ['nullable', 'string', 'max:1000'],
        ]);

        $review = Review::create([
            'lead_id' => $lead->id,
            'company_id' => $assignment->company_id,
            'reviewer_name' => $user->name ?: 'LockNear customer',
            'reviewer_email' => $user->email,
            'rating' => $validated['rating'],
            'speed_rating' => $validated['speed_rating'] ?? null,
            'communication_rating' => $validated['communication_rating'] ?? null,
            'professionalism_rating' => $validated['professionalism_rating'] ?? null,
            'price_rating' => $validated['price_rating'] ?? null,
            'body' => $validated['body'] ?? null,
            'is_verified' => true,
            'is_published' => true,
        ]);

        return response()->json(['data' => $review->fresh('lead')], 201);
    }

    private function canReviewLead(Lead $lead, $user): bool
    {
        if ($lead->status !== 'completed') {
            return false;
        }

        if ($lead->user_id && (int) $lead->user_id === (int) $user->id) {
            return true;
        }

        return $lead->email && $user->email && strtolower($lead->email) === strtolower($user->email);
    }
}
