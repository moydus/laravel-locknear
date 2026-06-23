<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateApiKey
{
    protected function configuredKeyMatches(Request $request, string $configured): bool
    {
        $provided = (string) $request->header('X-API-Key', '');

        return $provided !== '' && hash_equals($configured, $provided);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $configured = config('services.api_key');

        if (app()->environment('production') && empty($configured)) {
            return response()->json(['error' => 'API key not configured'], 503);
        }

        if ($configured && ! $this->configuredKeyMatches($request, $configured)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
