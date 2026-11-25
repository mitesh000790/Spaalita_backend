<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A checkout the customer has to correct before paying: an item that is no
 * longer sold, an invalid coupon, or a cart whose prices changed.
 *
 * The response carries a machine-readable `code` so the shop can fix the
 * cart (remove the item, drop the coupon, take the new prices).
 */
class CheckoutException extends RuntimeException
{
    public const ITEM_UNAVAILABLE = 'ITEM_UNAVAILABLE';

    public const INVALID_QUANTITY = 'INVALID_QUANTITY';

    public const COUPON_INVALID = 'COUPON_INVALID';

    public const CART_CHANGED = 'CART_CHANGED';

    public const AMOUNT_TOO_SMALL = 'AMOUNT_TOO_SMALL';

    /**
     * @param  array<string, mixed>  $details  extra response fields
     */
    public function __construct(
        string $message,
        public readonly string $reason,
        public readonly int $status = 400,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function toResponse(): JsonResponse
    {
        return response()->json(
            ['success' => false, 'code' => $this->reason, 'message' => $this->getMessage()] + $this->details,
            $this->status,
        );
    }
}
