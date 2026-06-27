<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderCustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['data' => []]);
        }

        $rows = $company->leads()
            ->join('leads', 'lead_assignments.lead_id', '=', 'leads.id')
            ->selectRaw('
                coalesce(leads.email, leads.phone) as customer_key,
                max(leads.customer_name) as customer_name,
                max(leads.email) as email,
                max(leads.phone) as phone,
                count(*) as jobs_count,
                max(lead_assignments.created_at) as last_job_at
            ')
            ->where(function ($query) {
                $query->whereNotNull('leads.phone')
                    ->orWhereNotNull('leads.email');
            })
            ->groupByRaw('coalesce(leads.email, leads.phone)')
            ->orderByDesc('last_job_at')
            ->paginate(25);

        return response()->json($rows);
    }
}
