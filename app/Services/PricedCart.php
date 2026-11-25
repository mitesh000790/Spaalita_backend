<?php

namespace App\Services;

use App\Models\CatalogItem;
use App\Models\Coupon;
use App\Support\Js;

/**
 * A checkout cart priced from the catalogue: what the customer is charged.
 */
final class PricedCart
{
    /**
     * @param  list<array{item: CatalogItem, name: string, price: float, qty: int, image: mixed}>  $lines
     */
    public function __construct(
        public readonly array $lines,
        public readonly float $subtotal,
        public readonly ?Coupon $coupon,
        public readonly float $discountAmount,
    ) {}

    /**
     * Amount charged, in dollars (subtotal minus the coupon discount, never below 0).
     */
    public function total(): float
    {
        return $this->totalInCents() / 100;
    }

    /**
     * Amount charged, in cents (what the Stripe PaymentIntent is created for).
     */
    public function totalInCents(): int
    {
        return (int) max(0, round(($this->subtotal - $this->discountAmount) * 100));
    }

    /**
     * The cart as the shop should show it (sent back when its prices are out of date).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (array $line) => [
                'productId' => $line['item']->id,
                'name' => $line['name'],
                'price' => Js::number($line['price']),
                'qty' => $line['qty'],
            ], $this->lines),
            'subtotal' => Js::number($this->subtotal),
            'coupon' => $this->coupon === null ? null : [
                'couponCode' => $this->coupon->code,
                'discountAmount' => Js::number($this->discountAmount),
                'freeShippingAmount' => 0,
                'totalAfterDiscount' => Js::number($this->total()),
            ],
            'totalAmount' => Js::number($this->total()),
        ];
    }
}
