<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CompanyMediaController extends Controller
{
    public function publicIndex(Company $company): JsonResponse
    {
        if (!$company->is_active) {
            abort(404);
        }

        $media = $company->media()
            ->where('is_public', true)
            ->where('source', 'uploaded')
            ->orderBy('sort_order')
            ->latest('id')
            ->get(['id', 'type', 'url', 'sort_order', 'metadata', 'created_at']);

        return response()->json(['data' => $media]);
    }

    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json(['data' => []]);
        }

        $media = $company->media()
            ->orderBy('sort_order')
            ->latest('id')
            ->get();

        return response()->json(['data' => $media]);
    }

    public function store(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $validated = $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'type' => ['nullable', 'string', Rule::in(['logo', 'cover', 'gallery', 'team', 'vehicle', 'license'])],
            'is_public' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'metadata' => ['nullable', 'array'],
        ]);

        $disk = config('filesystems.default');
        $type = $validated['type'] ?? 'gallery';
        $path = $validated['file']->store("companies/{$company->id}/{$type}", $disk);
        $url = Storage::disk($disk)->url($path);

        $media = $company->media()->create([
            'type' => $type,
            'url' => $url,
            'path' => $path,
            'disk' => $disk,
            'source' => 'uploaded',
            'is_public' => $request->boolean('is_public'),
            'sort_order' => $validated['sort_order'] ?? 0,
            'metadata' => $validated['metadata'] ?? null,
        ]);

        if ($type === 'logo') {
            $company->update(['logo_url' => $url]);
        }

        return response()->json(['data' => $media], 201);
    }

    public function destroy(Request $request, CompanyMedia $media): JsonResponse
    {
        $company = $request->user()->company;

        if (!$company || $media->company_id !== $company->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        if ($media->path && $media->disk) {
            Storage::disk($media->disk)->delete($media->path);
        }

        $media->delete();

        return response()->json(['success' => true]);
    }
}
