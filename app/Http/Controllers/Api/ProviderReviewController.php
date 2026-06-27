<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['data' => []]);
        }

        return response()->json(
            $company->reviews()
                ->with('lead:id,service_type,city,state,zip')
                ->latest()
                ->paginate(25)
        );
    }

    public function respond(Request $request, Review $review): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company || $review->company_id !== $company->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'response' => ['required', 'string', 'max:2000'],
        ]);

        $review->update([
            'provider_response' => $validated['response'],
            'provider_responded_at' => now(),
        ]);

        return response()->json(['data' => $review->fresh('lead')]);
    }
}
