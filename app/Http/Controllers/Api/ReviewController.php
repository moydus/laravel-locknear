<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class ReviewController extends Controller
{
    // GET /companies/{company}/reviews  — public
    public function index(Company $company): JsonResponse
    {
        if (!$company->is_active) {
            abort(404);
        }

        $reviews = $company->reviews()
            ->where('is_published', true)
            ->latest()
            ->paginate(10, [
                'id', 'rating', 'speed_rating', 'communication_rating',
                'professionalism_rating', 'price_rating', 'body', 'reviewer_name',
                'provider_response', 'provider_responded_at', 'is_verified', 'created_at',
            ]);

        return response()->json($reviews);
    }

    // GET /companies/{company}/reviews/summary  — public (Astro profil sayfaları için)
    public function summary(Company $company): JsonResponse
    {
        if (!$company->is_active) {
            abort(404);
        }

        $reviews = $company->reviews()->where('is_published', true);

        $counts = (clone $reviews)
            ->selectRaw('rating, count(*) as count')
            ->groupBy('rating')
            ->pluck('count', 'rating')
            ->toArray();

        $distribution = [];
        for ($i = 5; $i >= 1; $i--) {
            $distribution[$i] = $counts[$i] ?? 0;
        }

        $external = $company->sources()
            ->whereNotNull('rating')
            ->orderByDesc('last_synced_at')
            ->get(['source', 'external_url', 'rating', 'review_count', 'last_synced_at', 'metadata'])
            ->map(fn ($source) => [
                'source' => $source->source,
                'external_url' => $source->external_url,
                'rating' => $source->rating ? (float) $source->rating : null,
                'review_count' => $source->review_count,
                'last_synced_at' => $source->last_synced_at,
                'photo_count' => $source->metadata['photo_count'] ?? null,
                'has_photo_metadata' => !empty($source->metadata['photos']),
            ]);

        return response()->json([
            'locknear' => [
                'average' => round((float) ((clone $reviews)->avg('rating') ?? 0), 1),
                'total' => (clone $reviews)->count(),
                'distribution' => $distribution,
            ],
            'external' => [
                'primary' => [
                    'rating' => $company->rating ? (float) $company->rating : null,
                    'review_count' => $company->review_count,
                    'source' => $company->source,
                    'last_synced_at' => $company->source_last_synced_at,
                ],
                'sources' => $external,
                'note' => 'External ratings and review counts are imported source aggregates, not LockNear verified reviews.',
            ],
        ]);
    }
}
