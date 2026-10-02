<?php

namespace App\Support;

/**
 * Identity carried by a verified JWT (the original API's `req.user`).
 * Available in controllers through `$request->user()` on routes guarded by
 * the `auth.jwt` or `auth.jwt.optional` middleware.
 */
final class TokenUser
{
    /**
     * @param  array<string, mixed>  $claims
     */
    public function __construct(
        public readonly ?string $id,
        public readonly ?string $role,
        public readonly array $claims,
    ) {}

    /**
     * @param  array<string, mixed>  $claims
     */
    public static function fromClaims(array $claims): self
    {
        $id = $claims['id'] ?? null;
        $role = $claims['role'] ?? null;

        return new self(
            is_scalar($id) ? (string) $id : null,
            is_scalar($role) ? (string) $role : null,
            $claims,
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
