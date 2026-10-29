<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Exceptions\CastException;
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
 * Places an order: optionally registers a guest, applies the coupon
 * discount, creates the Stripe PaymentIntent for the chosen payment method
 * (card or Klarna) and stores the pending order.
 * A ticked "newsletter" checkbox also subscribes the customer's email.
 */
class CheckoutService
{
    public function __construct(
        private readonly StripeService $stripe,
        private readonly NewsletterService $newsletter,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated checkout payload
     * @return array{0: Order, 1: PaymentIntent}
     */
    public function placeOrder(array $input, ?string $userId): array
    {
        $guestInfo = $input['guestInfo'] ?? null;
        $billingAddress = $input['billingAddress'] ?? null;
        $shippingAddress = $input['shippingAddress'] ?? null;
        $coupon = Js::truthy($input['coupon'] ?? null) ? $input['coupon'] : null;

        if (! Js::truthy($userId) && Js::truthy($input['createAccount'] ?? null) && Js::truthy(self::field($guestInfo, 'email'))) {
            $userId = $this->findOrRegisterGuest($guestInfo, $billingAddress, $shippingAddress)->id;
        }

        $finalAmount = $input['totalAmount'];
        $discountAmount = self::field($coupon, 'discountAmount');

        if (Js::truthy($discountAmount)) {
            $discounted = $this->jsNumber($finalAmount) - $this->jsNumber($discountAmount);
            $finalAmount = is_nan($discounted) ? NAN : max(0, $discounted);
        }

        $amountInCents = round($this->jsNumber($finalAmount) * 100);

        if (! is_finite($amountInCents)) {
            throw new InvalidArgumentException('Invalid integer: NaN');
        }

        $paymentMethod = PaymentMethod::fromInput($input['paymentMethod'] ?? null);

        $paymentIntent = $this->stripe->createPaymentIntent(
            (int) $amountInCents,
            config('services.stripe.currency'),
            [
                'userId' => Js::truthy($userId) ? (string) $userId : 'guest',
                'email' => self::jsString(self::field($guestInfo, 'email') ?: ''),
                'couponCode' => self::jsString(self::field($coupon, 'code') ?: ''),
            ],
            $paymentMethod->stripePaymentMethodTypes(),
        );

        $order = DB::transaction(fn () => $this->createOrder(
            $input,
            Js::truthy($userId) ? (string) $userId : null,
            $finalAmount,
            $coupon,
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
    private function createOrder(array $input, ?string $userId, mixed $finalAmount, mixed $coupon, string $paymentIntentId, PaymentMethod $paymentMethod): Order
    {
        $guestInfo = $userId === null ? ($input['guestInfo'] ?? null) : null;
        $couponData = is_array($coupon) ? $coupon : ($coupon !== null ? [] : null);

        $order = Order::query()->create([
            'user_id' => $userId,
            'guest_name' => TypeCaster::string(self::field($guestInfo, 'name'), 'guestInfo.name'),
            'guest_email' => TypeCaster::string(self::field($guestInfo, 'email'), 'guestInfo.email'),
            'guest_phone' => TypeCaster::string(self::field($guestInfo, 'phone'), 'guestInfo.phone'),
            'total_amount' => TypeCaster::number($finalAmount, 'totalAmount'),
            'status' => 'pending',
            'payment_intent_id' => $paymentIntentId,
            'payment_method' => $paymentMethod->value,
            'payment_status' => 'pending',
            'coupon_code' => $couponData === null ? null : TypeCaster::string($couponData['code'] ?? null, 'coupon.code'),
            'coupon_discount_amount' => $couponData === null ? null : (TypeCaster::number($couponData['discountAmount'] ?? null, 'coupon.discountAmount') ?? 0),
            'coupon_free_shipping_amount' => $couponData === null ? null : (TypeCaster::number($couponData['freeShippingAmount'] ?? null, 'coupon.freeShippingAmount') ?? 0),
        ]);

        foreach (array_values($input['items']) as $position => $item) {
            if (! is_array($item) || ($item !== [] && array_is_list($item))) {
                throw CastException::make('Embedded', $item, "items.{$position}");
            }

            $order->items()->create([
                'product_id' => TypeCaster::objectId($item['productId'] ?? null, "items.{$position}.productId"),
                'name' => TypeCaster::string($item['name'] ?? null, "items.{$position}.name"),
                'price' => TypeCaster::number($item['price'] ?? null, "items.{$position}.price"),
                'qty' => TypeCaster::number($item['qty'] ?? null, "items.{$position}.qty"),
                'image' => TypeCaster::string($item['image'] ?? null, "items.{$position}.image"),
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

    private function jsNumber(mixed $value): float
    {
        return (float) (Js::toNumber($value) ?? NAN);
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
