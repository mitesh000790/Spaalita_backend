<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs each API request at debug level (silenced when LOG_LEVEL is higher).
 */
class LogIncomingRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::debug("Incoming request: {$request->method()} {$request->getRequestUri()}");

        return $next($request);
    }
}
