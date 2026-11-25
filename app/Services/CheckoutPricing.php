<?php

namespace App\Services;

use App\Exceptions\CheckoutException;
use App\Models\CatalogItem;
use App\Models\Coupon;
use App\Models\GiftCard;
use App\Models\Product;
use App\Support\Js;

/**
 * Prices a checkout from the catalogue, so the amount charged never comes
 * from the browser: each line takes the product's / gift card's current
 * price, and the coupon discount is calculated again on the server.
 *
 * The shop still sends the prices and total it displayed; when they differ
 * from the catalogue (a price or the coupon changed since the cart was
 * filled, or the request was tampered with) the order is refused with the
 * up-to-date cart, so the customer is never charged an amount they did not see.
 */
class CheckoutPricing
{
    /** Most units of one item in a single order */
    public const MAX_QTY = 1000;

    /** Stripe's minimum charge for CAD and USD: $0.50 */
    public const MINIMUM_CHARGE_CENTS = 50;

    public function __construct(private readonly CouponCalculator $calculator) {}

    /**
     * @param  list<mixed>  $items  order lines sent by the shop ({productId, name, price, qty, image})
     * @param  mixed  $coupon  {code, discountAmount} sent by the shop, or null
     *
     * @throws CheckoutException
     */
    public function price(array $items, mixed $coupon): PricedCart
    {
        $items = array_values($items);
        $catalog = $this->catalogItems($items);
        $lines = [];
        $subtotalCents = 0;

        foreach ($items as $index => $item) {
            $line = $this->line($item, $index, $catalog);
            $subtotalCents += (int) round($line['price'] * 100) * $line['qty'];
            $lines[] = $line;
        }

        $subtotal = $subtotalCents / 100;
        [$couponModel, $discount] = $this->applyCoupon($coupon, $lines);
        $cart = new PricedCart($lines, $subtotal, $couponModel, $discount);

        if ($cart->totalInCents() < self::MINIMUM_CHARGE_CENTS) {
            throw new CheckoutException('The order total must be at least $0.50.', CheckoutException::AMOUNT_TOO_SMALL);
        }

        return $cart;
    }

    /**
     * Refuse the order when the total the shop displayed is not the amount
     * that would be charged.
     *
     * @param  list<mixed>  $items  order lines sent by the shop
     *
     * @throws CheckoutException
     */
    public function assertShopTotalMatches(PricedCart $cart, array $items, mixed $shopSubtotal, mixed $coupon): void
    {
        $shopDiscount = Js::truthy($coupon) ? (self::field($coupon, 'discountAmount') ?? 0) : 0;
        $shopTotal = (float) (Js::toNumber($shopSubtotal) ?? NAN) - (float) (Js::toNumber($shopDiscount) ?? NAN);
        $shopTotalCents = is_finite($shopTotal) ? (int) max(0, round($shopTotal * 100)) : null;

        if ($shopTotalCents === $cart->totalInCents()) {
            return;
        }

        throw new CheckoutException(
            $this->changeMessage($cart, array_values($items), $shopDiscount),
            CheckoutException::CART_CHANGED,
            409,
            ['cart' => $cart->toArray()],
        );
    }

    /**
     * Active products and gift cards referenced by the lines, by id.
     *
     * @param  list<mixed>  $items
     * @return array<string, CatalogItem>
     */
    private function catalogItems(array $items): array
    {
        $ids = [];

        foreach ($items as $item) {
            $id = self::field($item, 'productId');

            if (is_string($id) && $id !== '') {
                $ids[$id] = true;
            }
        }

        if ($ids === []) {
            return [];
        }

        $ids = array_keys($ids);

        return GiftCard::query()->notInactive()->whereIn('id', $ids)->get()->keyBy('id')->all()
            + Product::query()->notInactive()->whereIn('id', $ids)->get()->keyBy('id')->all();
    }

    /**
     * @param  array<string, CatalogItem>  $catalog
     * @return array{item: CatalogItem, name: string, price: float, qty: int, image: mixed}
     */
    private function line(mixed $item, int $index, array $catalog): array
    {
        if (! is_array($item) || ($item !== [] && array_is_list($item))) {
            throw new CheckoutException('Your cart contains an invalid item. Please remove it and try again.', CheckoutException::ITEM_UNAVAILABLE, 400, ['itemIndex' => $index]);
        }

        $productId = $item['productId'] ?? null;
        $catalogItem = is_string($productId) ? ($catalog[$productId] ?? null) : null;
        $price = $catalogItem?->checkoutPrice() ?? 0.0;

        if ($catalogItem === null || $price <= 0) {
            throw new CheckoutException(
                self::itemLabel($item['name'] ?? null).' is no longer available. Please remove it from your cart and try again.',
                CheckoutException::ITEM_UNAVAILABLE,
                400,
                ['itemIndex' => $index, 'productId' => is_string($productId) ? $productId : null],
            );
        }

        $qty = Js::toNumber($item['qty'] ?? null);

        if ($qty === null || ! is_finite((float) $qty) || floor((float) $qty) !== (float) $qty || $qty < 1 || $qty > self::MAX_QTY) {
            throw new CheckoutException(
                'Please enter a quantity between 1 and '.self::MAX_QTY.' for '.$catalogItem->product_name.'.',
                CheckoutException::INVALID_QUANTITY,
                400,
                ['itemIndex' => $index, 'productId' => $catalogItem->id],
            );
        }

        return [
            'item' => $catalogItem,
            'name' => (string) $catalogItem->product_name,
            'price' => $price,
            'qty' => (int) $qty,
            'image' => $item['image'] ?? null,
        ];
    }

    /**
     * The coupon the shop applied, checked and calculated again on the cart's
     * catalogue prices.
     *
     * Lines are matched by product only: coupon category restrictions cannot
     * be set in the admin panel and the shop sends no category, so this is
     * the same result the cart's /coupon/apply call showed.
     *
     * @param  list<array{item: CatalogItem, price: float, qty: int}>  $lines
     * @return array{0: Coupon|null, 1: float}
     */
    private function applyCoupon(mixed $coupon, array $lines): array
    {
        $code = Js::truthy($coupon) ? self::field($coupon, 'code') : null;

        if (! is_string($code) || trim($code) === '') {
            return [null, 0.0];
        }

        $model = Coupon::query()
            ->with(Coupon::PRODUCT_RELATIONS)
            ->where('code', mb_strtoupper(trim($code)))
            ->where('status', 'active')
            ->first();

        if ($model === null) {
            throw new CheckoutException('Coupon not found or inactive', CheckoutException::COUPON_INVALID);
        }

        $result = $this->calculator->calculate($model, array_map(fn (array $line) => [
            'productId' => $line['item']->id,
            'price' => $line['price'],
            'qty' => $line['qty'],
            'category' => '',
        ], $lines), 0);

        if ($result->error !== null) {
            throw new CheckoutException($result->error, CheckoutException::COUPON_INVALID);
        }

        return [$model, $result->discountAmount];
    }

    /**
     * What changed between the shop's cart and the catalogue, for the customer.
     *
     * @param  list<mixed>  $items
     */
    private function changeMessage(PricedCart $cart, array $items, mixed $shopDiscount): string
    {
        $changes = [];

        foreach ($cart->lines as $index => $line) {
            $shopPrice = Js::toNumber(self::field($items[$index] ?? null, 'price'));

            if ($shopPrice === null || (int) round($shopPrice * 100) !== (int) round($line['price'] * 100)) {
                $changes[] = "The price of {$line['name']} is now ".self::money($line['price']).'.';
            }
        }

        $shopDiscount = Js::toNumber($shopDiscount);

        if ($shopDiscount === null || (int) round($shopDiscount * 100) !== (int) round($cart->discountAmount * 100)) {
            $changes[] = $cart->coupon === null
                ? 'No coupon discount applies to this order.'
                : "Your {$cart->coupon->code} discount is now ".self::money($cart->discountAmount).'.';
        }

        $changes[] = 'Your cart has been updated: please check the new total ('.self::money($cart->total()).') and place your order again.';

        return implode(' ', $changes);
    }

    private static function money(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    private static function itemLabel(mixed $name): string
    {
        return is_string($name) && trim($name) !== '' ? trim($name) : 'An item in your cart';
    }

    private static function field(mixed $object, string $key): mixed
    {
        return is_array($object) ? ($object[$key] ?? null) : null;
    }
}
