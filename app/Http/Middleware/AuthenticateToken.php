<?php

namespace App\Http\Middleware;

use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Requires a valid "Authorization: Bearer <token>" header.
 * The verified identity is available through `$request->user()`.
 */
class AuthenticateToken
{
    public function __construct(private readonly JwtService $jwt) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = self::tokenFrom($request);

        if ($token === null || $token === '') {
            return response()->json(['success' => false, 'message' => 'No token provided, authorization denied'], 401);
        }

        try {
            $user = $this->jwt->verify($token);
        } catch (Throwable) {
            return response()->json(['success' => false, 'message' => 'Invalid or expired token'], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    /**
     * The second space-separated part of the Authorization header.
     */
    public static function tokenFrom(Request $request): ?string
    {
        $header = (string) $request->header('Authorization', '');

        return explode(' ', $header)[1] ?? null;
    }
}
