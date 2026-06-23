<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_CUSTOMER,
        ]);

        $token = $user->createToken('locknear-customer')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->customerPayload($user),
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

        if (!$user->isCustomer()) {
            throw ValidationException::withMessages([
                'email' => ['This account is for locksmiths. Sign in at app.locknear.com.'],
            ]);
        }

        $token = $user->createToken('locknear-customer')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->customerPayload($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isCustomer()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json(['user' => $this->customerPayload($user)]);
    }

    public function leads(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->isCustomer()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $leads = Lead::query()
            ->where(function ($q) use ($user) {
                $this->applyCustomerLeadScope($q, $user);
            })
            ->with([
                'assignments' => fn ($q) => $q->latest()->with('company:id,name,phone,rating'),
            ])
            ->latest()
            ->limit(25)
            ->get([
                'id',
                'service_type',
                'status',
                'city',
                'state',
                'zip',
                'created_at',
                'customer_token',
                'assigned_at',
                'completed_at',
            ]);

        return response()->json(['data' => $leads]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isCustomer()) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function customerPayload(User $user): array
    {
        $jobs = Lead::query()
            ->where(function ($q) use ($user) {
                $this->applyCustomerLeadScope($q, $user);
            })
            ->where('status', 'completed')
            ->count();

        return [
            'id'         => $user->id,
            'name'       => $user->name,
            'email'      => $user->email,
            'phone'      => null,
            'rating'     => null,
            'jobs_count' => $jobs ?: null,
        ];
    }

    protected function applyCustomerLeadScope($query, User $user): void
    {
        $query->where('user_id', $user->id)
            ->orWhere('email', $user->email);
    }
}
