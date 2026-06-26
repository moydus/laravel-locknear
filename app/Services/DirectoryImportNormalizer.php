<?php

namespace App\Services;

class DirectoryImportNormalizer
{
    public function normalize(array $payload, string $source): array
    {
        return [
            'source' => $source,
            'external_id' => $payload['external_id'] ?? $payload['id'] ?? null,
            'google_place_id' => $payload['google_place_id'] ?? $payload['place_id'] ?? null,
            'apple_place_id' => $payload['apple_place_id'] ?? null,
            'yelp_business_id' => $payload['yelp_business_id'] ?? $payload['yelp_id'] ?? null,
            'name' => trim((string) ($payload['name'] ?? '')),
            'phone' => $payload['phone'] ?? $payload['phone_number'] ?? null,
            'email' => $payload['email'] ?? null,
            'website' => $payload['website'] ?? null,
            'address' => $payload['address'] ?? $payload['formatted_address'] ?? null,
            'city' => $payload['city'] ?? null,
            'state' => isset($payload['state']) ? strtoupper((string) $payload['state']) : null,
            'zip' => $payload['zip'] ?? $payload['postal_code'] ?? null,
            'latitude' => $payload['latitude'] ?? $payload['lat'] ?? null,
            'longitude' => $payload['longitude'] ?? $payload['lng'] ?? null,
            'rating' => $payload['rating'] ?? null,
            'review_count' => $payload['review_count'] ?? $payload['reviews_count'] ?? null,
            'external_url' => $payload['external_url'] ?? $payload['url'] ?? null,
            'raw' => $payload,
        ];
    }
}
