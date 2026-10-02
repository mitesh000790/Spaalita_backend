<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\GiftCard;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_normalises_the_code_and_tags_product_types(): void
    {
        $product = Product::factory()->create();
        $giftCard = GiftCard::factory()->create();

        $this->postJson('/api/coupon', [
            'code' => 'save15',
            'discountType' => 'percentage',
            'discountValue' => '15',
            'expiryDate' => '2030-01-01T00:00:00.000Z',
            'products' => [$product->id, $giftCard->id],
            'allowFreeShipping' => 'false',
        ], $this->asAdmin())
            ->assertCreated()
            ->assertJsonPath('data.code', 'SAVE15')
            ->assertJsonPath('data.discountValue', 15)
            ->assertJsonPath('data.allowFreeShipping', true)
            ->assertJsonPath('data.expiryDate', '2030-01-01T00:00:00.000Z')
            ->assertJsonPath('data.products', [$product->id, $giftCard->id])
            ->assertJsonPath('data.productsModel', ['Product', 'GiftCard']);

        $this->postJson('/api/coupon', ['code' => 'Save15', 'discountType' => 'fixed', 'discountValue' => 0, 'expiryDate' => '2030-01-01'], $this->asAdmin())
            ->assertStatus(409)->assertJsonPath('message', 'Coupon code already exists');
        $this->postJson('/api/coupon', ['code' => 'X'], $this->asAdmin())
            ->assertStatus(400)->assertJsonPath('message', 'Coupon code, discount type, amount, and expiry date are required');
    }

    public function test_details_include_product_names(): void
    {
        $product = Product::factory()->create(['product_name' => 'Cleanser']);
        $coupon = Coupon::factory()->create();
        $coupon->replaceProducts([['id' => $product->id, 'type' => 'Product']], excluded: false);

        $this->getJson("/api/coupon/{$coupon->id}", $this->asAdmin())
            ->assertOk()
            ->assertJsonPath('data.products', [['_id' => $product->id, 'productName' => 'Cleanser']]);
    }

    public function test_percentage_discount_applies_to_eligible_lines_only(): void
    {
        $eligible = Product::factory()->create();
        $other = Product::factory()->create();
        $coupon = Coupon::factory()->create(['code' => 'SAVE15', 'discount_value' => 15, 'categories' => ['Skin']]);
        $coupon->replaceProducts([['id' => $eligible->id, 'type' => 'Product']], excluded: false);

        $this->postJson('/api/coupon/apply', [
            'couponCode' => 'save15',
            'cartItems' => [
                ['productId' => $eligible->id, 'price' => 19.99, 'qty' => 3, 'category' => 'Skin'],
                ['productId' => $other->id, 'price' => 42, 'qty' => 1],
            ],
            'shippingAmount' => 7,
        ])->assertOk()->assertExactJson([
            'success' => true,
            'message' => 'Coupon applied successfully',
            'data' => [
                'couponCode' => 'SAVE15',
                'discountAmount' => 8.9955,
                'freeShippingAmount' => 0,
                'totalAfterDiscount' => 92.9745,
            ],
        ]);

        $this->assertSame(1, $coupon->fresh()->usage_count);
    }

    public function test_fixed_discount_free_shipping_and_usage_limit(): void
    {
        Coupon::factory()->fixed(5)->create(['code' => 'FIX5', 'allow_free_shipping' => true, 'usage_limit_per_coupon' => 1]);
        $cart = ['couponCode' => 'FIX5', 'cartItems' => [['productId' => 'a', 'price' => '3', 'qty' => '1']], 'shippingAmount' => '4.5'];

        $this->postJson('/api/coupon/apply', $cart)
            ->assertOk()
            ->assertJsonPath('data.discountAmount', 3)
            ->assertJsonPath('data.freeShippingAmount', '4.5')
            ->assertJsonPath('data.totalAfterDiscount', -4.5);

        $this->postJson('/api/coupon/apply', $cart)->assertStatus(400)->assertJsonPath('message', 'Coupon usage limit reached');
    }

    public function test_apply_rejections(): void
    {
        Coupon::factory()->expired()->create(['code' => 'OLD']);
        Coupon::factory()->create(['code' => 'MIN', 'min_order_amount' => 50]);
        $items = [['productId' => 'a', 'price' => 10, 'qty' => 1]];

        $this->postJson('/api/coupon/apply', ['cartItems' => $items])->assertStatus(400)->assertJsonPath('message', 'Coupon code is required');
        $this->postJson('/api/coupon/apply', ['couponCode' => 'OLD', 'cartItems' => []])->assertStatus(400)->assertJsonPath('message', 'Cart items are required');
        $this->postJson('/api/coupon/apply', ['couponCode' => 'NOPE', 'cartItems' => $items])->assertNotFound()->assertJsonPath('message', 'Coupon not found or inactive');
        $this->postJson('/api/coupon/apply', ['couponCode' => 'old', 'cartItems' => $items])->assertStatus(400)->assertJsonPath('message', 'Coupon has expired');
        $this->postJson('/api/coupon/apply', ['couponCode' => 'MIN', 'cartItems' => $items])->assertStatus(400)->assertJsonPath('message', 'Minimum order amount for this coupon is 50');
    }
}
