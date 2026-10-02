<?php

namespace App\Services;

/**
 * Outcome of applying a coupon to a cart. When $error is set the coupon
 * cannot be applied and the other values are meaningless.
 */
final class CouponCalculation
{
    public function __construct(
        public readonly ?string $error = null,
        public readonly float $discountAmount = 0.0,
        public readonly mixed $freeShippingAmount = 0,
        public readonly float $totalAfterDiscount = 0.0,
    ) {}

    public static function rejected(string $reason): self
    {
        return new self(error: $reason);
    }
}
