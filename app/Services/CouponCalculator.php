<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CouponProduct;
use App\Support\Js;
use InvalidArgumentException;

/**
 * Applies a store coupon to a cart.
 *
 * A cart line is eligible when it matches the coupon's product and
 * category restrictions. Fixed discounts are capped at the eligible amount;
 * percentage discounts apply to the eligible amount. Coupons that allow
 * free shipping also discount the given shipping amount.
 */
class CouponCalculator
{
    /**
     * @param  list<mixed>  $cartItems  [{productId, price, qty, category}, ...]
     */
    public function calculate(Coupon $coupon, array $cartItems, mixed $shippingAmount): CouponCalculation
    {
        if ($coupon->expiry_date->isPast()) {
            return CouponCalculation::rejected('Coupon has expired');
        }

        if (Js::truthy($coupon->usage_limit_per_coupon) && $coupon->usage_count >= $coupon->usage_limit_per_coupon) {
            return CouponCalculation::rejected('Coupon usage limit reached');
        }

        $includedIds = $this->existingProductIds($coupon->includedProducts);
        $excludedIds = $this->existingProductIds($coupon->excludedProducts);
        $categories = $coupon->categories ?? [];
        $excludedCategories = $coupon->exclude_categories ?? [];

        $cartTotal = 0.0;
        $eligibleAmount = 0.0;

        foreach ($cartItems as $item) {
            if ($item === null) {
                throw new InvalidArgumentException("Cannot read properties of null (reading 'productId')");
            }

            $item = is_array($item) ? $item : [];
            $productId = $item['productId'] ?? null;
            $category = Js::truthy($item['category'] ?? null) ? $item['category'] : '';

            $productIncluded = $includedIds === [] || in_array($productId, $includedIds, true);
            $productExcluded = $excludedIds !== [] && in_array($productId, $excludedIds, true);
            $categoryExcluded = in_array($category, $excludedCategories, true);
            $categoryIncluded = $categories === [] || in_array($category, $categories, true);

            $lineTotal = $this->numberField($item, 'price') * $this->numberField($item, 'qty');
            $cartTotal += $lineTotal;

            if ($productIncluded && ! $productExcluded && $categoryIncluded && ! $categoryExcluded) {
                $eligibleAmount += $lineTotal;
            }
        }

        if (Js::truthy($coupon->min_order_amount) && $cartTotal < $coupon->min_order_amount) {
            return CouponCalculation::rejected(
                'Minimum order amount for this coupon is '.Js::numberToString($coupon->min_order_amount)
            );
        }

        if (Js::truthy($coupon->max_order_amount) && $cartTotal > $coupon->max_order_amount) {
            return CouponCalculation::rejected(
                'Maximum order amount for this coupon is '.Js::numberToString($coupon->max_order_amount)
            );
        }

        if ($eligibleAmount === 0.0) {
            return CouponCalculation::rejected('No eligible products in cart for this coupon');
        }

        $discount = match ($coupon->discount_type) {
            'fixed' => is_nan($eligibleAmount) ? NAN : min($coupon->discount_value, $eligibleAmount),
            'percentage' => ($eligibleAmount * $coupon->discount_value) / 100,
            default => 0.0,
        };

        $freeShipping = $coupon->allow_free_shipping ? $shippingAmount : 0;

        return new CouponCalculation(
            discountAmount: (float) $discount,
            freeShippingAmount: $freeShipping,
            totalAfterDiscount: $cartTotal - $discount - (Js::toNumber($freeShipping) ?? NAN),
        );
    }

    /**
     * Ids of the listed products / gift cards that still exist.
     *
     * @param  iterable<CouponProduct>  $links
     * @return list<string>
     */
    private function existingProductIds(iterable $links): array
    {
        $ids = [];

        foreach ($links as $link) {
            if ($link->productable !== null) {
                $ids[] = $link->productable->id;
            }
        }

        return $ids;
    }

    /**
     * A cart field as a JavaScript number (missing or non-numeric values are NaN).
     *
     * @param  array<string, mixed>  $item
     */
    private function numberField(array $item, string $field): float
    {
        if (! array_key_exists($field, $item)) {
            return NAN;
        }

        return (float) (Js::toNumber($item[$field]) ?? NAN);
    }
}
