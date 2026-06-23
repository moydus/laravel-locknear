<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\SupportInquiryMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'    => ['required', 'string', 'max:100'],
            'email'   => ['required', 'email', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'topic'   => ['required', 'string', 'in:booking,account,provider,billing,other'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $inbox = config('services.support_inbox', 'support@locknear.com');

        try {
            Mail::to($inbox)->send(new SupportInquiryMail($validated));
        } catch (\Exception $e) {
            Log::warning('Support inquiry email failed: ' . $e->getMessage());

            return response()->json(['error' => 'Unable to send message right now. Please email us directly.'], 503);
        }

        return response()->json(['success' => true], 201);
    }
}
