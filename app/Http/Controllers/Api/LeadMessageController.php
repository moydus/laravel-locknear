<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\LeadMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadMessageController extends Controller
{
    public function __construct(private LeadMessageService $messages) {}

    public function indexByTrackToken(string $token): JsonResponse
    {
        $lead = Lead::where('customer_token', $token)->firstOrFail();

        if (!$this->messages->canExchangeMessages($lead)) {
            return response()->json(['data' => [], 'enabled' => false]);
        }

        return response()->json([
            'enabled' => true,
            'data' => $this->messages->serializeThread($lead),
        ]);
    }

    public function storeByTrackToken(Request $request, string $token): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $lead = Lead::where('customer_token', $token)->firstOrFail();

        if (!$this->messages->canExchangeMessages($lead)) {
            return response()->json(['error' => 'Messaging is not available for this request yet.'], 403);
        }

        $message = $this->messages->postCustomerMessage($lead, $validated['body']);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'sender' => $message->sender,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    public function indexForProvider(Request $request, Lead $lead): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $this->messages->assertProviderAssignment($lead, $company);

        return response()->json([
            'enabled' => $this->messages->canExchangeMessages($lead),
            'data' => $this->messages->serializeThread($lead),
        ]);
    }

    public function storeForProvider(Request $request, Lead $lead): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company'], 403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $assignment = $this->messages->assertProviderAssignment($lead, $company);

        if (!in_array($assignment->status, ['accepted', 'en_route', 'arrived', 'completed'], true)) {
            return response()->json(['error' => 'Accept the lead before messaging.'], 403);
        }

        $message = $this->messages->postProviderMessage($lead, $company, $validated['body']);

        return response()->json([
            'message' => [
                'id' => $message->id,
                'sender' => $message->sender,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ],
        ], 201);
    }
}
