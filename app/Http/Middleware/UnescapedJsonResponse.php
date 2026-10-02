<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encodes JSON responses without escaping "/" or Unicode characters, so
 * paths read "/uploads/x.jpg" rather than "\/uploads\/x.jpg" (as JSON.stringify does).
 */
class UnescapedJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return $response;
    }
}
