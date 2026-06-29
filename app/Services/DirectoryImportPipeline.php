<?php

namespace App\Services;

use App\Enums\CompanyLifecycleStatus;
use App\Enums\ProviderStatus;
use App\Models\Company;
use App\Models\CompanyIdentity;
use App\Models\CompanySource;
use App\Models\CompanyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DirectoryImportPipeline
{
    public function __construct(
        private DirectoryImportNormalizer $normalizer,
        private CompanyIdentityMatcher $matcher,
        private DomainEventRecorder $events,
    ) {}

    public function import(array $payload, string $source, int $ownerUserId): Company
    {
        $data = $this->normalizer->normalize($payload, $source);

        if ($data['name'] === '') {
            throw new \InvalidArgumentException('Directory import payload requires a company name.');
        }

        return DB::transaction(function () use ($data, $ownerUserId) {
            $company = $this->matcher->find($data);

            if (!$company) {
                $company = Company::create([
                    'user_id' => $ownerUserId,
                    'name' => $data['name'],
                    'slug' => $this->uniqueSlug($data['name']),
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'website' => $this->matcher->normalizeWebsite($data['website']),
                    'address' => $data['address'],
                    'city' => $data['city'],
                    'state' => $data['state'],
                    'zip' => $data['zip'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'service_areas' => $data['zip'] ? [$data['zip']] : null,
                    'google_place_id' => $data['google_place_id'],
                    'rating' => $data['rating'] ?? 0,
                    'review_count' => $data['review_count'] ?? 0,
                    'is_active' => true,
                    'is_online' => false,
                    'is_claimed' => false,
                    'provider_status' => ProviderStatus::Pending,
                    'lifecycle_status' => CompanyLifecycleStatus::Unclaimed,
                    'source' => $data['source'],
                    'source_last_synced_at' => now(),
                ]);
            }

            CompanySource::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'source' => $data['source'],
                    'external_id' => $data['external_id'],
                ],
                [
                    'external_url' => $data['external_url'],
                    'rating' => $data['rating'],
                    'review_count' => $data['review_count'],
                    'last_synced_at' => now(),
                    'metadata' => array_filter([
                        'photos' => $data['photos'],
                        'photo_count' => $data['photo_count'],
                        'opening_hours' => $data['opening_hours'],
                        'attributes' => $data['attributes'],
                        'raw' => $data['raw'],
                    ], fn ($value) => $value !== null && $value !== []),
                ],
            );

            $this->syncDefaultServices($company, $data);

            CompanyIdentity::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'source' => $data['source'],
                    'external_id' => $data['external_id'],
                ],
                [
                    'google_place_id' => $data['google_place_id'],
                    'apple_place_id' => $data['apple_place_id'],
                    'yelp_business_id' => $data['yelp_business_id'],
                    'website' => $this->matcher->normalizeWebsite($data['website']),
                    'phone_normalized' => $this->matcher->normalizePhone($data['phone']),
                    'match_confidence' => $this->matcher->confidence($data, $company),
                    'status' => 'matched',
                    'matched_at' => now(),
                    'match_signals' => [
                        'source' => $data['source'],
                        'phone' => filled($data['phone']),
                        'website' => filled($data['website']),
                        'google_place_id' => filled($data['google_place_id']),
                    ],
                ],
            );

            $this->events->record('DirectoryCompanyImported', $company, [
                'source' => $data['source'],
                'external_id' => $data['external_id'],
                'city' => $company->city,
                'state' => $company->state,
                'zip' => $company->zip,
            ], ['company_id' => $company->id]);

            return $company;
        });
    }

    private function syncDefaultServices(Company $company, array $data): void
    {
        $rawText = strtolower(json_encode($data['raw'] ?? [], JSON_UNESCAPED_SLASHES) ?: '');
        $nameText = strtolower((string) $company->name);
        $text = $nameText . ' ' . $rawText;

        $services = [
            'emergency-locksmith',
            '24-hour-locksmith',
            'mobile-locksmith',
            'automotive-locksmith',
            'car-locksmith',
            'car-lockout',
            'locked-keys-in-car',
            'car-key-replacement',
            'lost-car-keys',
            'key-fob-programming',
            'ignition-repair',
        ];

        if (str_contains($text, 'house') || str_contains($text, 'residential')) {
            $services[] = 'house-lockout';
            $services[] = 'residential-locksmith';
        }

        if (str_contains($text, 'commercial') || str_contains($text, 'business')) {
            $services[] = 'commercial-locksmith';
            $services[] = 'office-lockout';
        }

        foreach (array_unique($services) as $service) {
            CompanyService::firstOrCreate(
                ['company_id' => $company->id, 'service_type' => $service],
                ['price' => null, 'is_active' => true],
            );
        }
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 1;

        while (Company::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        return $slug;
    }
}
