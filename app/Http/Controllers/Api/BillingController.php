<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeadAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function leadCharges(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $charges = LeadAssignment::query()
            ->where('company_id', $company->id)
            ->where(function ($q) {
                $q->whereNotNull('stripe_charge_id')
                    ->orWhereIn('status', ['accepted', 'en_route', 'arrived', 'completed']);
            })
            ->with(['lead:id,service_type,city,state,zip,created_at'])
            ->latest()
            ->limit(50)
            ->get([
                'id',
                'lead_id',
                'lead_cost',
                'stripe_charge_id',
                'status',
                'created_at',
                'accepted_at',
                'completed_at',
            ]);

        return response()->json(['data' => $charges]);
    }
}
