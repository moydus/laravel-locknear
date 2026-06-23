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
                'id', 'rating', 'body', 'reviewer_name',
                'is_verified', 'created_at',
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

        return response()->json([
            'average' => round($company->rating, 1),
            'total'   => $company->review_count,
            'distribution' => $distribution,
        ]);
    }
}
