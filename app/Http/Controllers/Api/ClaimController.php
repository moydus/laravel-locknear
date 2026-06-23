<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClaimController extends Controller
{
    // GET /claim/{token}  — token bilgisini döner (panel sayfası yüklenirken)
    public function show(string $token): JsonResponse
    {
        $company = Company::where('claim_token', $token)
            ->whereNull('deleted_at')
            ->first();

        if (!$company) {
            return response()->json(['error' => 'Invalid or expired claim link'], 404);
        }

        if ($company->is_claimed) {
            return response()->json(['error' => 'This listing has already been claimed'], 409);
        }

        return response()->json([
            'company' => [
                'id'    => $company->id,
                'name'  => $company->name,
                'city'  => $company->city,
                'state' => $company->state,
                'phone' => $company->phone,
            ],
        ]);
    }

    // POST /claim/{token}  — mevcut oturumdaki kullanıcıya firmayı bağlar
    public function claim(Request $request, string $token): JsonResponse
    {
        $company = Company::where('claim_token', $token)
            ->whereNull('deleted_at')
            ->first();

        if (!$company) {
            return response()->json(['error' => 'Invalid or expired claim link'], 404);
        }

        if ($company->is_claimed) {
            return response()->json(['error' => 'Already claimed'], 409);
        }

        $user = $request->user();

        // Kullanıcının zaten başka bir firması varsa reddet
        if ($user->company && $user->company->id !== $company->id) {
            return response()->json(['error' => 'Your account is already linked to another company'], 422);
        }

        $company->update([
            'user_id'     => $user->id,
            'is_claimed'  => true,
            'is_active'   => true,
            'claimed_at'  => now(),
            'claim_token' => null,       // tek kullanım
            'source'      => 'claimed',
        ]);

        return response()->json([
            'success' => true,
            'company' => ['id' => $company->id, 'name' => $company->name],
        ]);
    }

    // POST /claim/manual  — claim token olmadan yeni firma oluşturur (doğrudan kayıt)
    public function manual(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->company) {
            return response()->json(['error' => 'Already has a company'], 422);
        }

        $validated = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'zip'   => ['required', 'string', 'size:5'],
            'city'  => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2'],
        ]);

        $company = Company::create([
            'user_id'    => $user->id,
            'name'       => $validated['name'],
            'slug'       => str($validated['name'])->slug()->append('-' . Str::random(6)),
            'phone'      => $validated['phone'],
            'zip'        => $validated['zip'],
            'city'       => $validated['city'],
            'state'      => strtoupper($validated['state']),
            'is_claimed' => true,
            'is_active'  => false,  // admin onayı veya profil tamamlanması gerekiyor
            'claimed_at' => now(),
            'source'     => 'manual',
        ]);

        return response()->json([
            'success' => true,
            'company' => ['id' => $company->id, 'name' => $company->name],
        ], 201);
    }
}
