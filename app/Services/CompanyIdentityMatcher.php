<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanyIdentity;

class CompanyIdentityMatcher
{
    public function find(array $identity): ?Company
    {
        $googlePlaceId = $identity['google_place_id'] ?? null;
        $applePlaceId = $identity['apple_place_id'] ?? null;
        $yelpBusinessId = $identity['yelp_business_id'] ?? null;
        $externalId = $identity['external_id'] ?? null;
        $source = $identity['source'] ?? null;
        $phone = $this->normalizePhone($identity['phone'] ?? null);
        $website = $this->normalizeWebsite($identity['website'] ?? null);

        if (!$googlePlaceId && !$applePlaceId && !$yelpBusinessId && !$phone && !$website && !($source && $externalId)) {
            return null;
        }

        $existingIdentity = CompanyIdentity::query()
            ->when($googlePlaceId, fn ($query) => $query->orWhere('google_place_id', $googlePlaceId))
            ->when($applePlaceId, fn ($query) => $query->orWhere('apple_place_id', $applePlaceId))
            ->when($yelpBusinessId, fn ($query) => $query->orWhere('yelp_business_id', $yelpBusinessId))
            ->when($phone, fn ($query) => $query->orWhere('phone_normalized', $phone))
            ->when($source && $externalId, fn ($query) => $query->orWhere(fn ($inner) => $inner
                ->where('source', $source)
                ->where('external_id', $externalId)))
            ->first();

        if ($existingIdentity) {
            return $existingIdentity->company;
        }

        return Company::query()
            ->when($googlePlaceId, fn ($query) => $query->orWhere('google_place_id', $googlePlaceId))
            ->when($phone, fn ($query) => $query->orWhereRaw("regexp_replace(coalesce(phone, ''), '[^0-9]', '', 'g') = ?", [$phone]))
            ->when($website, fn ($query) => $query->orWhere('website', $website))
            ->first();
    }

    public function confidence(array $identity, ?Company $company): float
    {
        if (!$company) {
            return 0;
        }

        $score = 0;
        $phone = $this->normalizePhone($identity['phone'] ?? null);
        $companyPhone = $this->normalizePhone($company->phone);

        if (($identity['google_place_id'] ?? null) && $identity['google_place_id'] === $company->google_place_id) {
            $score += 45;
        }

        if ($phone && $phone === $companyPhone) {
            $score += 35;
        }

        if (($identity['website'] ?? null) && $this->normalizeWebsite($identity['website']) === $this->normalizeWebsite($company->website)) {
            $score += 15;
        }

        if (($identity['zip'] ?? null) && $identity['zip'] === $company->zip) {
            $score += 5;
        }

        return min(100, $score);
    }

    public function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits !== '' ? $digits : null;
    }

    public function normalizeWebsite(?string $website): ?string
    {
        if (!$website) {
            return null;
        }

        return rtrim(strtolower(preg_replace('#^https?://#', '', $website)), '/');
    }
}
