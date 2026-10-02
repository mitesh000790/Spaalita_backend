<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Identifies the caller when a valid token is sent; missing or invalid
 * tokens are treated as a guest (`$request->user()` returns null).
 */
class OptionalAuthenticateToken
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = AuthenticateToken::tokenFrom($request);
        $user = null;

        if ($token !== null && $token !== '') {
            try {
                $user = $this->jwt->verify($token);
            } catch (Throwable) {
                $user = null;
            }
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
