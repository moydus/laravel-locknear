<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProviderDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => ProviderDocument::where('company_id', $company->id)
                ->latest()
                ->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $validated = $request->validate([
            'type' => ['required', 'string', 'in:business_license,insurance,id,background_check,other'],
            'document_number' => ['nullable', 'string', 'max:100'],
            'issuing_state' => ['nullable', 'string', 'max:100'],
            'expires_at' => ['nullable', 'date'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
            'metadata' => ['nullable', 'array'],
        ]);

        $fileUrl = null;
        if ($request->hasFile('file')) {
            $disk = config('filesystems.default');
            $path = $request->file('file')->store("companies/{$company->id}/documents", $disk);
            $fileUrl = Storage::disk($disk)->url($path);
        }

        $document = ProviderDocument::create([
            'company_id' => $company->id,
            'status' => 'pending',
            'file_url' => $fileUrl,
            ...collect($validated)->except('file')->all(),
        ]);

        return response()->json(['data' => $document], 201);
    }
}
