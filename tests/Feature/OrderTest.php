<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_validation(): void
    {
        $this->postJson('/api/orders', ['totalAmount' => 10])
            ->assertStatus(400)->assertExactJson(['success' => false, 'message' => 'Order items and total amount are required']);
        $this->postJson('/api/orders', ['items' => [['name' => 'x']], 'totalAmount' => 10])
            ->assertStatus(400)->assertJsonPath('message', 'Guest information required');
    }

    public function test_guest_checkout_creates_a_payment_intent_and_a_pending_order(): void
    {
        $calls = [];
        $this->fakeStripe($calls);
        $product = Product::factory()->create(['product_name' => 'Cleanser', 'price' => 19.99]);
        $giftCard = GiftCard::factory()->create(['product_name' => 'Gift Card $50', 'price' => 50]);
        Coupon::factory()->fixed(5)->create(['code' => 'FIX5']);

        $response = $this->postJson('/api/orders', [
            'items' => [
                ['productId' => $product->id, 'name' => 'Old name', 'price' => 19.99, 'qty' => 2],
                ['productId' => $giftCard->id, 'name' => 'Gift', 'price' => 50, 'qty' => 1],
            ],
            'totalAmount' => 89.98,
            'guestInfo' => ['name' => 'Guest', 'email' => 'guest@example.com'],
            'shippingAddress' => ['city' => 'Laval'],
            'coupon' => ['code' => 'fix5', 'discountAmount' => 5],
        ]);

        $response->assertCreated()
            ->assertJsonPath('message', 'Order created successfully')
            ->assertJsonPath('clientSecret', 'pi_test_1_secret')
            ->assertJsonPath('data.user', null)
            ->assertJsonPath('data.guestInfo', ['name' => 'Guest', 'email' => 'guest@example.com'])
            ->assertJsonPath('data.totalAmount', 84.98)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.paymentIntentId', 'pi_test_1')
            ->assertJsonPath('data.paymentMethod', 'card')
            ->assertJsonPath('data.items.0.productId', ['_id' => $product->id, 'productName' => 'Cleanser'])
            ->assertJsonPath('data.items.0.productName', 'Cleanser')
            ->assertJsonPath('data.items.0.price', 19.99)
            // Names come from the catalogue, not from the request
            ->assertJsonPath('data.items.1.productName', 'Gift Card $50')
            ->assertJsonPath('data.shippingAddress.city', 'Laval')
            ->assertJsonPath('data.shippingAddress.zip', '')
            ->assertJsonPath('data.coupon', ['code' => 'FIX5', 'discountAmount' => 5, 'freeShippingAmount' => 0]);

        // Card payments keep Stripe's default payment methods (no payment_method_types)
        $this->assertSame([[
            'amount' => 8498,
            'currency' => 'cad',
            'metadata' => ['userId' => 'guest', 'email' => 'guest@example.com', 'couponCode' => 'FIX5'],
            'paymentMethodTypes' => [],
        ]], $calls);
    }

    public function test_klarna_checkout_creates_a_klarna_payment_intent(): void
    {
        $calls = [];
        $this->fakeStripe($calls);

        $this->postJson('/api/orders', [
            'items' => [$this->cartLine(120)],
            'totalAmount' => 120,
            'guestInfo' => ['name' => 'Kim', 'email' => 'kim@example.com'],
            'paymentMethod' => 'klarna',
        ])->assertCreated()
            ->assertJsonPath('clientSecret', 'pi_test_1_secret')
            ->assertJsonPath('data.paymentMethod', 'klarna')
            ->assertJsonPath('data.paymentStatus', 'pending')
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame(['klarna'], $calls[0]['paymentMethodTypes']);
        $this->assertSame(12000, $calls[0]['amount']);
        $this->assertSame('klarna', Order::query()->sole()->payment_method);
    }

    public function test_checkout_rejects_an_unknown_payment_method(): void
    {
        $calls = [];
        $this->fakeStripe($calls);

        $this->postJson('/api/orders', [
            'items' => [$this->cartLine(10)],
            'totalAmount' => 10,
            'guestInfo' => ['name' => 'G'],
            'paymentMethod' => 'paypal',
        ])->assertStatus(400)->assertExactJson(['success' => false, 'message' => 'Invalid payment method']);

        $this->assertSame([], $calls);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_charges_in_the_configured_currency(): void
    {
        config(['services.stripe.currency' => 'usd']);
        $calls = [];
        $this->fakeStripe($calls);
        $order = ['items' => [$this->cartLine(10)], 'totalAmount' => 10, 'guestInfo' => ['name' => 'G']];

        $this->postJson('/api/orders', $order)->assertCreated();
        $this->postJson('/api/orders', $order + ['paymentMethod' => 'klarna'])->assertCreated();

        $this->assertSame(['usd', 'usd'], array_column($calls, 'currency'));
    }

    public function test_there_is_no_endpoint_that_creates_a_payment_for_any_amount(): void
    {
        $calls = [];
        $this->fakeStripe($calls);

        $this->postJson('/api/payment/create-payment-intent', ['amount' => 1])->assertNotFound();
        $this->assertSame([], $calls);
    }

    public function test_checkout_charges_catalogue_prices_not_the_prices_sent(): void
    {
        $calls = [];
        $this->fakeStripe($calls);
        $product = Product::factory()->create(['product_name' => 'Gift Card $100', 'price' => 100]);

        // A tampered (or out of date) cart: $1 instead of $100
        $this->postJson('/api/orders', [
            'items' => [['productId' => $product->id, 'name' => 'Gift Card $100', 'price' => 1, 'qty' => 2]],
            'totalAmount' => 2,
            'guestInfo' => ['name' => 'Eve', 'email' => 'eve@example.com'],
            'createAccount' => true,
            'billingAddress' => ['firstName' => 'Eve', 'lastName' => 'X'],
        ])->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'CART_CHANGED')
            ->assertJsonPath('message', 'The price of Gift Card $100 is now $100.00. Your cart has been updated: please check the new total ($200.00) and place your order again.')
            ->assertJsonPath('cart.items', [['productId' => $product->id, 'name' => 'Gift Card $100', 'price' => 100, 'qty' => 2]])
            ->assertJsonPath('cart.subtotal', 200)
            ->assertJsonPath('cart.coupon', null)
            ->assertJsonPath('cart.totalAmount', 200);

        // Nothing was created: no payment, no order, no guest account
        $this->assertSame([], $calls);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_checkout_charges_the_sale_price(): void
    {
        $calls = [];
        $this->fakeStripe($calls);
        $line = $this->cartLine(100, 1, ['regular_price' => '100', 'sale_price' => '79.5']);

        $this->assertSame(79.5, $line['price']);
        $this->postJson('/api/orders', ['items' => [$line], 'totalAmount' => 79.5, 'guestInfo' => ['name' => 'G']])
            ->assertCreated()
            ->assertJsonPath('data.totalAmount', 79.5);
        $this->assertSame(7950, $calls[0]['amount']);
    }

    public function test_coupon_discounts_are_calculated_on_the_server(): void
    {
        $calls = [];
        $this->fakeStripe($calls);
        Coupon::factory()->create(['code' => 'SAVE10', 'discount_type' => 'percentage', 'discount_value' => 10]);
        $order = ['items' => [$this->cartLine(25, 2)], 'totalAmount' => 50, 'guestInfo' => ['name' => 'G']];

        // A discount bigger than the coupon gives is refused, with the real discount
        $this->postJson('/api/orders', $order + ['coupon' => ['code' => 'SAVE10', 'discountAmount' => 49.5]])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CART_CHANGED')
            ->assertJsonPath('message', 'Your SAVE10 discount is now $5.00. Your cart has been updated: please check the new total ($45.00) and place your order again.')
            ->assertJsonPath('cart.coupon', ['couponCode' => 'SAVE10', 'discountAmount' => 5, 'freeShippingAmount' => 0, 'totalAfterDiscount' => 45])
            ->assertJsonPath('cart.totalAmount', 45);

        // A discount sent without a coupon code is not applied
        $this->postJson('/api/orders', $order + ['coupon' => ['discountAmount' => 49.5]])
            ->assertStatus(409)
            ->assertJsonPath('cart.coupon', null)
            ->assertJsonPath('cart.totalAmount', 50);

        $this->postJson('/api/orders', $order + ['coupon' => ['code' => 'SAVE10', 'discountAmount' => 5]])
            ->assertCreated()
            ->assertJsonPath('data.totalAmount', 45)
            ->assertJsonPath('data.coupon', ['code' => 'SAVE10', 'discountAmount' => 5, 'freeShippingAmount' => 0]);

        $this->assertSame([4500], array_column($calls, 'amount'));
    }

    public function test_invalid_coupons_are_refused_at_checkout(): void
    {
        $calls = [];
        $this->fakeStripe($calls);
        Coupon::factory()->expired()->create(['code' => 'OLD']);
        Coupon::factory()->fixed(5)->create(['code' => 'ONCE', 'usage_limit_per_coupon' => 1, 'usage_count' => 1]);
        $order = ['items' => [$this->cartLine(20)], 'totalAmount' => 20, 'guestInfo' => ['name' => 'G']];

        foreach (['OLD' => 'Coupon has expired', 'ONCE' => 'Coupon usage limit reached', 'NOPE' => 'Coupon not found or inactive'] as $code => $message) {
            $this->postJson('/api/orders', $order + ['coupon' => ['code' => $code, 'discountAmount' => 5]])
                ->assertStatus(400)
                ->assertExactJson(['success' => false, 'code' => 'COUPON_INVALID', 'message' => $message]);
        }

        $this->assertSame([], $calls);
    }

    public function test_items_that_are_not_for_sale_are_refused(): void
    {
        $calls = [];
        $this->fakeStripe($calls);
        $inactive = Product::factory()->inactive()->create(['product_name' => 'Old Serum']);
        $guest = ['guestInfo' => ['name' => 'G']];

        $this->postJson('/api/orders', ['items' => [['name' => 'Made up', 'price' => 1, 'qty' => 1]], 'totalAmount' => 1] + $guest)
            ->assertStatus(400)
            ->assertExactJson([
                'success' => false,
                'code' => 'ITEM_UNAVAILABLE',
                'message' => 'Made up is no longer available. Please remove it from your cart and try again.',
                'itemIndex' => 0,
                'productId' => null,
            ]);

        $this->postJson('/api/orders', ['items' => [$this->cartLine(10), ['productId' => $inactive->id, 'name' => 'Old Serum', 'price' => 5, 'qty' => 1]], 'totalAmount' => 15] + $guest)
            ->assertStatus(400)
            ->assertJsonPath('code', 'ITEM_UNAVAILABLE')
            ->assertJsonPath('itemIndex', 1)
            ->assertJsonPath('productId', $inactive->id);

        foreach ([0, -1, 1.5, 'abc', 1001] as $qty) {
            $line = ['qty' => $qty] + $this->cartLine(10);
            $this->postJson('/api/orders', ['items' => [$line], 'totalAmount' => 10] + $guest)
                ->assertStatus(400)
                ->assertJsonPath('code', 'INVALID_QUANTITY')
                ->assertJsonPath('productId', $line['productId']);
        }

        $this->assertSame([], $calls);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_totals_below_the_stripe_minimum_are_refused(): void
    {
        $this->fakeStripe();
        Coupon::factory()->create(['code' => 'FREE', 'discount_type' => 'percentage', 'discount_value' => 100]);

        $this->postJson('/api/orders', ['items' => [$this->cartLine(10)], 'totalAmount' => 10, 'guestInfo' => ['name' => 'G'], 'coupon' => ['code' => 'FREE', 'discountAmount' => 10]])
            ->assertStatus(400)
            ->assertExactJson(['success' => false, 'code' => 'AMOUNT_TOO_SMALL', 'message' => 'The order total must be at least $0.50.']);
    }

    public function test_guests_can_create_an_account_at_checkout(): void
    {
        $this->fakeStripe();
        $billing = ['firstName' => 'Ann', 'lastName' => 'Lee', 'address1' => '1 St', 'postcode' => 'Z9', 'phone' => '7', 'country' => 'CA', 'city' => 'Laval'];

        $this->postJson('/api/orders', [
            'items' => [$this->cartLine(50)],
            'totalAmount' => 50,
            'guestInfo' => ['email' => 'ANN@example.com'],
            'createAccount' => true,
            'billingAddress' => $billing,
        ])->assertCreated()->assertJsonPath('data.guestInfo', null);

        $user = User::query()->where('email', 'ann@example.com')->firstOrFail();
        $this->assertSame('annlee', $user->user_name);
        $this->assertSame('1 St', $user->address);
        $this->assertSame('Laval', $user->billingAddress->city);
        $this->assertSame($user->id, Order::query()->first()->user_id);
    }

    public function test_listing_is_scoped_to_the_customer_unless_admin(): void
    {
        $this->fakeStripe();
        $jane = User::factory()->create(['email' => 'jane@example.com']);
        $bob = User::factory()->create();
        $item = ['items' => [$this->cartLine(10)], 'totalAmount' => 10];

        $this->postJson('/api/orders', $item, $this->asUser($jane))->assertCreated();
        $this->postJson('/api/orders', $item, $this->asUser($bob))->assertCreated();
        $this->travel(1)->seconds();
        $this->postJson('/api/orders', $item + ['guestInfo' => ['name' => 'Gina', 'email' => 'gina@example.com']])->assertCreated();

        $this->getJson('/api/orders', $this->asAdmin())
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('pagination.totalPages', 1)
            ->assertJsonPath('data.0.user', null)
            ->assertJsonPath('data.0.guestInfo.email', 'gina@example.com')
            ->assertJsonMissingPath('data.1.user.password');
        $this->getJson('/api/orders?search=gina@', $this->asAdmin())->assertJsonCount(1, 'data');

        $this->getJson('/api/orders', $this->asUser($jane))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.email', 'jane@example.com');

        $this->getJson('/api/orders?search=JANE@', $this->asAdmin())->assertJsonCount(1, 'data');
    }

    public function test_admins_update_and_delete_orders(): void
    {
        $this->fakeStripe();
        $headers = $this->asAdmin();
        $id = $this->postJson('/api/orders', ['items' => [$this->cartLine(5)], 'totalAmount' => 5, 'guestInfo' => ['name' => 'G']])->json('data._id');

        $this->putJson("/api/orders/{$id}/status", ['status' => 'completed'], $headers)
            ->assertOk()->assertJsonPath('message', 'Order status updated')->assertJsonPath('data.status', 'completed');

        $this->putJson("/api/orders/{$id}", ['paymentStatus' => 'succeeded', 'billingAddress' => ['city' => 'Paris'], 'totalAmount' => 1], $headers)
            ->assertOk()
            ->assertJsonPath('data.paymentStatus', 'succeeded')
            ->assertJsonPath('data.billingAddress.city', 'Paris')
            ->assertJsonPath('data.totalAmount', 5);

        $this->deleteJson("/api/orders/{$id}", [], $headers)->assertOk()->assertJsonPath('message', 'Order deleted successfully');
        $this->deleteJson("/api/orders/{$id}", [], $headers)->assertNotFound()->assertJsonPath('message', 'Order not found');
    }

    public function test_stripe_webhook_marks_orders_paid_when_the_signature_is_valid(): void
    {
        $order = Order::query()->create(['total_amount' => 10, 'payment_intent_id' => 'pi_123']);
        $payload = self::paymentIntentEvent('payment_intent.succeeded', 'pi_123');

        $this->postStripeWebhook($payload)->assertOk()->assertExactJson(['received' => true]);

        $this->assertSame(['paid', 'succeeded'], [$order->fresh()->status, $order->fresh()->payment_status]);

        $this->call('POST', '/api/orders/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=1,v1=bad'], $payload)
            ->assertStatus(400);
    }

    public function test_stripe_webhook_marks_a_cancelled_or_declined_klarna_payment_failed(): void
    {
        $order = Order::query()->create(['total_amount' => 10, 'payment_intent_id' => 'pi_klarna', 'payment_method' => 'klarna']);

        $this->postStripeWebhook(self::paymentIntentEvent('payment_intent.payment_failed', 'pi_klarna'))->assertOk();

        $this->assertSame(['failed', 'failed'], [$order->fresh()->status, $order->fresh()->payment_status]);

        // Paying the same order again (the customer retries with Klarna) marks it paid
        $this->postStripeWebhook(self::paymentIntentEvent('payment_intent.succeeded', 'pi_klarna'))->assertOk();

        $this->assertSame(['paid', 'succeeded'], [$order->fresh()->status, $order->fresh()->payment_status]);
    }

    public function test_a_paid_order_counts_its_coupon_once_and_stays_paid(): void
    {
        $coupon = Coupon::factory()->fixed(5)->create(['code' => 'FIX5']);
        $order = Order::query()->create(['total_amount' => 10, 'payment_intent_id' => 'pi_coupon', 'coupon_code' => 'FIX5', 'coupon_discount_amount' => 5]);
        $succeeded = self::paymentIntentEvent('payment_intent.succeeded', 'pi_coupon');

        // Stripe may deliver the same event more than once
        $this->postStripeWebhook($succeeded)->assertOk();
        $this->postStripeWebhook($succeeded)->assertOk();
        $this->assertSame(1, $coupon->fresh()->usage_count);

        // ...and in any order: an earlier failed attempt reported late does not undo the payment
        $this->postStripeWebhook(self::paymentIntentEvent('payment_intent.payment_failed', 'pi_coupon'))->assertOk();
        $this->assertSame(['paid', 'succeeded'], [$order->fresh()->status, $order->fresh()->payment_status]);
    }

    private static function paymentIntentEvent(string $type, string $paymentIntentId): string
    {
        return json_encode(['id' => 'evt_1', 'object' => 'event', 'type' => $type, 'data' => ['object' => ['id' => $paymentIntentId, 'object' => 'payment_intent']]]);
    }

    /**
     * POST a webhook payload signed with STRIPE_WEBHOOK_SECRET (whsec_testing in phpunit.xml).
     */
    private function postStripeWebhook(string $payload): TestResponse
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", 'whsec_testing');

        return $this->call('POST', '/api/orders/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $payload);
    }
}
