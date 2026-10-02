<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\User;
use App\Support\TokenUser;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

/**
 * Issues and verifies the HS256 access tokens used by the admin panel and
 * the customer website. Token claims and lifetimes match the original
 * Node.js API (admin: id/username/email/role, 1 hour; user: id/email/role, 1 day).
 */
class JwtService
{
    private const MIN_SECRET_LENGTH = 32;

    /**
     * @param  array{admin: int, user: int}  $ttl
     */
    public function __construct(
        private readonly ?string $secret,
        private readonly string $algorithm,
        private readonly array $ttl,
    ) {}

    public function issueAdminToken(Admin $admin): string
    {
        return $this->encode([
            'id' => $admin->id,
            'username' => $admin->username,
            'email' => $admin->email,
            'role' => $admin->role,
        ], $this->ttl['admin']);
    }

    public function issueUserToken(User $user): string
    {
        return $this->encode([
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
        ], $this->ttl['user']);
    }

    /**
     * Verify the signature and expiry of a token.
     *
     * @throws \Throwable when the token is malformed, tampered with or expired
     */
    public function verify(string $token): TokenUser
    {
        $claims = (array) JWT::decode($token, new Key($this->secret(), $this->algorithm));

        return TokenUser::fromClaims($claims);
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function encode(array $claims, int $ttl): string
    {
        $issuedAt = time();

        return JWT::encode($claims + ['iat' => $issuedAt, 'exp' => $issuedAt + $ttl], $this->secret(), $this->algorithm);
    }

    private function secret(): string
    {
        if ($this->secret === null || strlen($this->secret) < self::MIN_SECRET_LENGTH) {
            throw new RuntimeException(
                'JWT_SECRET must be set to at least '.self::MIN_SECRET_LENGTH.' characters (run: php artisan jwt:secret)'
            );
        }

        return $this->secret;
    }
}
