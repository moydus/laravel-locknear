<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\VerifyEmailMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_name' => ['required', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_BUSINESS,
        ]);

        $company = \App\Models\Company::create([
            'user_id' => $user->id,
            'name' => $validated['company_name'],
            'slug' => str($validated['company_name'])->slug()->append('-' . $user->id),
        ]);

        try {
            Mail::to($user)->send(new VerifyEmailMail($user));
        } catch (\Throwable $e) {
            report($e);
        }

        $token = $user->createToken('app-locknear')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->formatUser($user->fresh()->load('company')),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (!$user->isBusiness()) {
            throw ValidationException::withMessages([
                'email' => ['This is a customer account. Sign in at locknear.com.'],
            ]);
        }

        $token = $user->createToken('app-locknear')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->formatUser($user->load('company')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $this->formatUser($request->user()->load('company')),
        ]);
    }

    private function formatUser(User $user): array
    {
        $company = $user->company;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'company' => $company ? [
                'id' => $company->id,
                'name' => $company->name,
                'business_type' => $company->business_type,
                'onboarding_completed_at' => $company->onboarding_completed_at?->toIso8601String(),
            ] : null,
        ];
    }
}
