<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProviderAccountUser;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderTeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company) {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => ProviderAccountUser::query()
                ->where('company_id', $company->id)
                ->with('user:id,name,email')
                ->orderByRaw("role = 'owner' desc")
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
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:owner,dispatcher,technician,admin'],
            'permissions' => ['nullable', 'array'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        $identity = $user
            ? ['company_id' => $company->id, 'user_id' => $user->id]
            : ['company_id' => $company->id, 'email' => $validated['email']];

        $member = ProviderAccountUser::updateOrCreate(
            $identity,
            [
                'email' => $validated['email'],
                'role' => $validated['role'],
                'status' => $user ? 'active' : 'invited',
                'permissions' => $validated['permissions'] ?? [],
                'invited_at' => now(),
                'joined_at' => $user ? now() : null,
            ],
        );

        return response()->json(['data' => $member->fresh('user')], 201);
    }

    public function update(Request $request, ProviderAccountUser $member): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company || $member->company_id !== $company->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'role' => ['sometimes', 'string', 'in:owner,dispatcher,technician,admin'],
            'status' => ['sometimes', 'string', 'in:active,invited,suspended,removed'],
            'permissions' => ['sometimes', 'nullable', 'array'],
        ]);

        $member->update($validated);

        return response()->json(['data' => $member->fresh('user')]);
    }

    public function destroy(Request $request, ProviderAccountUser $member): JsonResponse
    {
        $company = $request->user()->company;
        if (!$company || $member->company_id !== $company->id) {
            return response()->json(['error' => 'Not found'], 404);
        }

        $member->update(['status' => 'removed']);

        return response()->json(['success' => true]);
    }
}
