<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\CheckoutException;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\User;
use App\Models\UserAddress;
use App\Support\Js;
use App\Support\TypeCaster;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Stripe\PaymentIntent;
use Throwable;

/**
 * Places an order: prices the cart from the catalogue (CheckoutPricing),
 * optionally registers a guest, creates the Stripe PaymentIntent for the
 * chosen payment method (card or Klarna) and stores the pending order.
 * A ticked "newsletter" checkbox also subscribes the customer's email.
 *
 * The amount charged is always the catalogue total: the prices and total
 * sent by the shop are only compared with it (see CheckoutPricing).
 */
class CheckoutService
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly NewsletterService $newsletter,
        private readonly CheckoutPricing $pricing,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated checkout payload
     * @return array{0: Order, 1: PaymentIntent}
     *
     * @throws CheckoutException when the cart must be corrected before paying
     */
    public function placeOrder(array $input, ?string $userId): array
    {
        $guestInfo = $input['guestInfo'] ?? null;
        $billingAddress = $input['billingAddress'] ?? null;
        $shippingAddress = $input['shippingAddress'] ?? null;
        $coupon = Js::truthy($input['coupon'] ?? null) ? $input['coupon'] : null;
        $items = is_array($input['items']) ? array_values($input['items']) : [];

        // Before anything is created: a cart that must be corrected leaves no account, payment or order behind
        $cart = $this->pricing->price($items, $coupon);
        $this->pricing->assertShopTotalMatches($cart, $items, $input['totalAmount'] ?? null, $coupon);

        if (! Js::truthy($userId) && Js::truthy($input['createAccount'] ?? null) && Js::truthy(self::field($guestInfo, 'email'))) {
            $userId = $this->findOrRegisterGuest($guestInfo, $billingAddress, $shippingAddress)->id;
        }

        $paymentMethod = PaymentMethod::fromInput($input['paymentMethod'] ?? null);

        $paymentIntent = $this->stripe->createPaymentIntent(
            $cart->totalInCents(),
            config('services.stripe.currency'),
            [
                'userId' => Js::truthy($userId) ? (string) $userId : 'guest',
                'email' => self::jsString(self::field($guestInfo, 'email') ?: ''),
                'couponCode' => $cart->coupon?->code ?? '',
            ],
            $paymentMethod->stripePaymentMethodTypes(),
        );

        $order = DB::transaction(fn () => $this->createOrder(
            $input,
            Js::truthy($userId) ? (string) $userId : null,
            $cart,
            $paymentIntent->id,
            $paymentMethod,
        ));

        if (Js::truthy($input['newsletter'] ?? null)) {
            $this->joinNewsletter($input, $order->user_id);
        }

        return [$order, $paymentIntent];
    }

    /**
     * Subscribe the account email (or the guest/billing email) to the newsletter.
     * A failed sign-up is logged and never fails the order.
     *
     * @param  array<string, mixed>  $input
     */
    private function joinNewsletter(array $input, ?string $userId): void
    {
        try {
            $user = $userId !== null ? User::query()->find($userId) : null;
            $email = $user?->email
                ?? (self::field($input['guestInfo'] ?? null, 'email') ?: self::field($input['billingAddress'] ?? null, 'email'));

            if (is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false) {
                $this->newsletter->subscribe($email, 'checkout', $user);
            }
        } catch (Throwable $exception) {
            Log::warning('Checkout newsletter sign-up failed: '.$exception->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    /**
     * Store the order with the catalogue prices and totals of the priced cart.
     */
    private function createOrder(array $input, ?string $userId, PricedCart $cart, string $paymentIntentId, PaymentMethod $paymentMethod): Order
    {
        $guestInfo = $userId === null ? ($input['guestInfo'] ?? null) : null;

        $order = Order::query()->create([
            'user_id' => $userId,
            'guest_name' => TypeCaster::string(self::field($guestInfo, 'name'), 'guestInfo.name'),
            'guest_email' => TypeCaster::string(self::field($guestInfo, 'email'), 'guestInfo.email'),
            'guest_phone' => TypeCaster::string(self::field($guestInfo, 'phone'), 'guestInfo.phone'),
            'total_amount' => $cart->total(),
            'status' => 'pending',
            'payment_intent_id' => $paymentIntentId,
            'payment_method' => $paymentMethod->value,
            'payment_status' => 'pending',
            'coupon_code' => $cart->coupon?->code,
            'coupon_discount_amount' => $cart->coupon === null ? null : $cart->discountAmount,
            'coupon_free_shipping_amount' => $cart->coupon === null ? null : 0,
        ]);

        foreach ($cart->lines as $position => $line) {
            $order->items()->create([
                'product_id' => $line['item']->id,
                'name' => $line['name'],
                'price' => $line['price'],
                'qty' => $line['qty'],
                'image' => TypeCaster::string($line['image'], "items.{$position}.image"),
                'position' => $position,
            ]);
        }

        foreach ([OrderAddress::TYPE_SHIPPING => 'shippingAddress', OrderAddress::TYPE_BILLING => 'billingAddress'] as $type => $field) {
            $address = array_key_exists($field, $input) ? $input[$field] : [];

            if ($address !== null) {
                $order->addresses()->create(['type' => $type] + self::orderAddressAttributes($address, $field));
            }
        }

        return $order;
    }

    /**
     * Attach the order to the account with the guest's email, creating the
     * account (with a random password) from the billing address if needed.
     */
    private function findOrRegisterGuest(array $guestInfo, mixed $billingAddress, mixed $shippingAddress): User
    {
        $email = TypeCaster::string($guestInfo['email'], 'email', ['lowercase' => true]);
        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! is_array($billingAddress)) {
            throw new InvalidArgumentException("Cannot read properties of undefined (reading 'firstName')");
        }

        $userName = mb_strtolower(preg_replace(
            '/\s/u',
            '',
            self::jsString($billingAddress['firstName'] ?? null).self::jsString($billingAddress['lastName'] ?? null),
        ));

        $user = DB::transaction(function () use ($userName, $billingAddress, $shippingAddress, $email) {
            $user = User::query()->create([
                'user_name' => $userName,
                'first_name' => TypeCaster::string($billingAddress['firstName'] ?? null, 'firstName', ['trim' => true]),
                'last_name' => TypeCaster::string($billingAddress['lastName'] ?? null, 'lastName', ['trim' => true]),
                'email' => $email,
                'phone' => TypeCaster::string($billingAddress['phone'] ?? null, 'phone', ['trim' => true]),
                'country' => TypeCaster::string($billingAddress['country'] ?? null, 'country', ['trim' => true]),
                'city' => TypeCaster::string($billingAddress['city'] ?? null, 'city', ['trim' => true]),
                'address' => TypeCaster::string($billingAddress['address1'] ?? null, 'address', ['trim' => true]),
                'postal_code' => TypeCaster::string($billingAddress['postcode'] ?? null, 'postalCode', ['trim' => true]),
                'password' => Hash::make(self::randomPassword()),
            ]);

            $user->addresses()->create(['type' => UserAddress::TYPE_BILLING] + self::userAddressAttributes($billingAddress, UserAddress::TYPE_BILLING));

            if (is_array($shippingAddress)) {
                $user->addresses()->create(['type' => UserAddress::TYPE_SHIPPING] + self::userAddressAttributes($shippingAddress, UserAddress::TYPE_SHIPPING));
            }

            return $user;
        });

        Log::info('Guest converted to registered user: '.$email);

        return $user;
    }

    /**
     * @return array<string, string>
     */
    private static function orderAddressAttributes(mixed $address, string $field): array
    {
        $address = is_array($address) ? $address : [];
        $attributes = [];

        foreach (OrderAddress::FIELDS as $key => $column) {
            $attributes[$column] = TypeCaster::string($address[$key] ?? null, "{$field}.{$key}") ?? '';
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $address
     * @return array<string, string|null>
     */
    private static function userAddressAttributes(array $address, string $type): array
    {
        $attributes = [];

        foreach (UserAddress::FIELDS[$type] as $key => $column) {
            $attributes[$column] = TypeCaster::string($address[$key] ?? null, "{$type}.{$key}");
        }

        return $attributes;
    }

    /**
     * Read a property of a request object (null when absent or not an object).
     */
    private static function field(mixed $object, string $key): mixed
    {
        return is_array($object) ? ($object[$key] ?? null) : null;
    }

    /**
     * JavaScript String(value) for template literals.
     */
    private static function jsString(mixed $value): string
    {
        return match (true) {
            $value === null => 'undefined',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => Js::numberToString($value),
            is_array($value) => array_is_list($value) ? implode(',', array_map(self::jsString(...), $value)) : '[object Object]',
            default => (string) $value,
        };
    }

    /**
     * Eight random lowercase letters/digits (the original used Math.random().toString(36)).
     */
    private static function randomPassword(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';

        for ($i = 0; $i < 8; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
