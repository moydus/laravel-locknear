<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'zip' => ['required', 'string', 'size:5', 'regex:/^[0-9]+$/'],
            'service_type' => ['required', 'string', 'in:car-lockout,car-key-replacement,house-lockout,lock-rekey,commercial,emergency'],
            'phone' => ['required', 'string', 'min:10'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $lead = Lead::create([
            ...$validated,
            'status' => 'new',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'source' => $request->header('Referer', 'direct'),
        ]);

        return response()->json(['success' => true, 'lead_id' => $lead->id], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $leads = Lead::latest()->paginate(25);
        return response()->json($leads);
    }

    public function show(Lead $lead): JsonResponse
    {
        return response()->json($lead->load('assignments.company'));
    }
}
