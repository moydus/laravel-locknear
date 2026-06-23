<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyController extends Controller
{
    // GET /companies  — public (Astro sitesi kullanır)
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('q') && config('scout.driver') === 'meilisearch') {
            return $this->searchIndex($request);
        }

        $query = Company::where('is_active', true)
            ->with('services:id,company_id,service_type,is_active')
            ->select([
                'id', 'name', 'slug', 'phone', 'city', 'state', 'zip',
                'latitude', 'longitude', 'rating', 'review_count',
                'is_verified', 'is_claimed', 'logo_url', 'description',
            ]);

        if ($request->filled('zip')) {
            $query->where(function ($q) use ($request) {
                $q->where('zip', $request->zip)
                  ->orWhereJsonContains('service_areas', $request->zip);
            });
        }

        if ($request->filled('state')) {
            $query->where('state', strtoupper($request->state));
        }

        if ($request->filled('city')) {
            $query->where('city', 'like', $request->city . '%');
        }

        if ($request->filled('service')) {
            $query->whereHas('services', fn ($q) =>
                $q->where('service_type', $request->service)->where('is_active', true)
            );
        }

        $companies = $query
            ->orderByDesc('is_claimed')
            ->orderByDesc('rating')
            ->orderByDesc('review_count')
            ->paginate(20);

        return response()->json($companies);
    }

    // GET /companies/{company}  — public
    public function show(Company $company): JsonResponse
    {
        if (!$company->is_active) {
            abort(404);
        }

        return response()->json(
            $company->load('services:id,company_id,service_type,price,is_active')
                    ->makeHidden(['stripe_customer_id', 'claim_token', 'user_id'])
        );
    }

    // GET /company/me  — authenticated (firma paneli)
    public function me(Request $request): JsonResponse
    {
        Company::markStaleOffline();

        $company = $request->user()->company;

        if (!$company) {
            return response()->json(['data' => null]);
        }

        $company->refresh();

        return response()->json([
            'data' => $this->companyWithPresence($company->load('services', 'subscription.package')),
        ]);
    }

    // POST /company/me/heartbeat — authenticated (presence ping)
    public function heartbeat(Request $request): JsonResponse
    {
        Company::markStaleOffline();

        $company = $request->user()->company;

        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        if ($company->is_online) {
            $company->forceFill([
                'last_seen_at' => now(),
            ])->save();
        }

        $company->refresh();

        return response()->json([
            'data' => $this->companyWithPresence($company),
        ]);
    }

    // PUT /company/me  — authenticated (firma paneli profil kaydet)
    public function update(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $validated = $request->validate([
            'name'               => ['sometimes', 'string', 'max:255'],
            'phone'              => ['sometimes', 'string', 'max:20'],
            'email'              => ['sometimes', 'email', 'max:255'],
            'website'            => ['sometimes', 'nullable', 'url', 'max:255'],
            'description'        => ['sometimes', 'nullable', 'string', 'max:2000'],
            'address'            => ['sometimes', 'nullable', 'string', 'max:255'],
            'city'               => ['sometimes', 'nullable', 'string', 'max:100'],
            'state'              => ['sometimes', 'nullable', 'string', 'size:2'],
            'zip'                => ['sometimes', 'nullable', 'string', 'size:5'],
            'latitude'           => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude'          => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'google_place_id'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'formatted_address'  => ['sometimes', 'nullable', 'string', 'max:500'],
            'address_components' => ['sometimes', 'nullable', 'array'],
            'place_source'       => ['sometimes', 'nullable', 'string', 'in:google,manual'],
            'license_number'     => ['sometimes', 'nullable', 'string', 'max:100'],
            'is_insured'         => ['sometimes', 'boolean'],
            'is_online'          => ['sometimes', 'boolean'],
            'service_areas'      => ['sometimes', 'nullable', 'array'],
            'service_areas.*'    => ['string', 'size:5'],
        ]);

        if (!empty($validated['google_place_id'])) {
            $validated['place_source'] = $validated['place_source'] ?? 'google';
            $validated['place_verified_at'] = now();
            if (!empty($validated['formatted_address']) && empty($validated['address'])) {
                $validated['address'] = $validated['formatted_address'];
            }
        }

        // Slug sadece name değişince güncellenir, unique kalması için id eklenir
        if (isset($validated['name']) && $validated['name'] !== $company->name) {
            $validated['slug'] = str($validated['name'])->slug()->append('-' . $company->id);
        }

        if (($validated['is_online'] ?? false) === true) {
            $phone = $validated['phone'] ?? $company->phone;
            $address = $validated['address'] ?? $company->address;

            if (!$phone || !$address) {
                return response()->json([
                    'message' => 'Complete your phone number and address before going online.',
                ], 422);
            }

            $validated['is_active'] = true;
            $validated['last_seen_at'] = now();
        }

        $company->update($validated);

        if (!$company->is_active && $company->phone && $company->address) {
            $company->update(['is_active' => true]);
        }

        return response()->json(['data' => $this->companyWithPresence($company->fresh())]);
    }

    // POST /company/me/logo — authenticated (firma logo → R2/local disk)
    public function uploadLogo(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $disk = config('filesystems.default');
        $path = $validated['logo']->store("companies/{$company->id}", $disk);
        $url = Storage::disk($disk)->url($path);

        $company->update(['logo_url' => $url]);

        return response()->json(['data' => ['logo_url' => $url]]);
    }

    // DELETE /company/me  — authenticated
    public function destroy(Request $request): JsonResponse
    {
        $company = $request->user()->company;

        if (!$company) {
            return response()->json(['error' => 'No company found'], 404);
        }

        $company->delete();

        return response()->json(['message' => 'Company deleted']);
    }

    private function searchIndex(Request $request): JsonResponse
    {
        $builder = Company::search($request->string('q')->toString())
            ->where('is_active', true);

        if ($request->filled('state')) {
            $builder->where('state', strtoupper($request->state));
        }

        if ($request->filled('zip')) {
            $builder->where('zip', $request->zip);
        }

        $results = $builder
            ->query(fn ($query) => $query->with('services:id,company_id,service_type,is_active'))
            ->paginate(20);

        return response()->json($results);
    }

    private function companyWithPresence(Company $company): array
    {
        $data = $company->makeHidden(['stripe_customer_id', 'claim_token'])->toArray();

        return array_merge($data, $company->presencePayload());
    }
}
