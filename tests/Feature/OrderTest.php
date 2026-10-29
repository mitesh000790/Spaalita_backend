<?php

namespace Tests\Feature;

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
        $product = Product::factory()->create(['product_name' => 'Cleanser']);

        $response = $this->postJson('/api/orders', [
            'items' => [['productId' => $product->id, 'name' => 'Old name', 'price' => 19.99, 'qty' => 2], ['name' => 'Gift', 'price' => 50, 'qty' => 1]],
            'totalAmount' => 89.98,
            'guestInfo' => ['name' => 'Guest', 'email' => 'guest@example.com'],
            'shippingAddress' => ['city' => 'Laval'],
            'coupon' => ['code' => 'FIX5', 'discountAmount' => 5],
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
            ->assertJsonPath('data.items.1.productName', 'Gift')
            ->assertJsonPath('data.shippingAddress.city', 'Laval')
            ->assertJsonPath('data.shippingAddress.zip', '')
            ->assertJsonPath('data.coupon', ['code' => 'FIX5', 'discountAmount' => 5, 'freeShippingAmount' => 0]);

        // Card payments keep Stripe's default payment methods (no payment_method_types)
        $this->assertSame([[
            'amount' => 8498,
            'currency' => 'usd',
            'metadata' => ['userId' => 'guest', 'email' => 'guest@example.com', 'couponCode' => 'FIX5'],
            'paymentMethodTypes' => [],
        ]], $calls);
    }

    public function test_klarna_checkout_creates_a_klarna_payment_intent(): void
    {
        $calls = [];
        $this->fakeStripe($calls);

        $this->postJson('/api/orders', [
            'items' => [['name' => 'Gift', 'price' => 120, 'qty' => 1]],
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
            'items' => [['name' => 'Gift', 'price' => 10, 'qty' => 1]],
            'totalAmount' => 10,
            'guestInfo' => ['name' => 'G'],
            'paymentMethod' => 'paypal',
        ])->assertStatus(400)->assertExactJson(['success' => false, 'message' => 'Invalid payment method']);

        $this->assertSame([], $calls);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_checkout_charges_in_the_configured_currency(): void
    {
        config(['services.stripe.currency' => 'cad']);
        $calls = [];
        $this->fakeStripe($calls);
        $order = ['items' => [['name' => 'Gift', 'price' => 10, 'qty' => 1]], 'totalAmount' => 10, 'guestInfo' => ['name' => 'G']];

        $this->postJson('/api/orders', $order)->assertCreated();
        $this->postJson('/api/orders', $order + ['paymentMethod' => 'klarna'])->assertCreated();
        $this->postJson('/api/payment/create-payment-intent', ['amount' => 10])->assertOk();

        $this->assertSame(['cad', 'cad', 'cad'], array_column($calls, 'currency'));
    }

    public function test_guests_can_create_an_account_at_checkout(): void
    {
        $this->fakeStripe();
        $billing = ['firstName' => 'Ann', 'lastName' => 'Lee', 'address1' => '1 St', 'postcode' => 'Z9', 'phone' => '7', 'country' => 'CA', 'city' => 'Laval'];

        $this->postJson('/api/orders', [
            'items' => [['name' => 'Gift', 'price' => 50, 'qty' => 1]],
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
        $item = ['items' => [['name' => 'Gift', 'price' => 10, 'qty' => 1]], 'totalAmount' => 10];

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
        $id = $this->postJson('/api/orders', ['items' => [['name' => 'x']], 'totalAmount' => 5, 'guestInfo' => ['name' => 'G']])->json('data._id');

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
