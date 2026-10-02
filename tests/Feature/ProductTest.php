<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GiftCard;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');
    }

    public function test_create_with_repeated_multipart_fields_keeps_every_file_and_category(): void
    {
        $skin = Category::factory()->create(['name' => 'Skin']);
        $lips = Category::factory()->create(['name' => 'Lips']);

        $response = $this->multipart('POST', '/api/products', [
            ['productName', '  Lipstick  '],
            ['sku', ' LIP-1 '],
            ['price', '42.5'],
            ['qty', '3'],
            ['description', 'Red'],
            ['categories', $skin->id],
            ['categories', $lips->id],
            ['shipping_required', 'false'],
        ], [
            ['productImages', 'a.png', 'image/png'],
            ['productImages', 'b.png', 'image/png'],
        ], $this->asAdmin());

        $response->assertCreated()
            ->assertJsonPath('message', 'Product created successfully')
            ->assertJsonPath('data.productName', 'Lipstick')
            ->assertJsonPath('data.sku', 'LIP-1')
            ->assertJsonPath('data.price', 42.5)
            ->assertJsonPath('data.qty', 3)
            ->assertJsonPath('data.categories', [$skin->id, $lips->id])
            ->assertJsonPath('data.shipping_required', false)
            ->assertJsonPath('data.tax_status', 'none')
            ->assertJsonPath('data.stock_status', 'instock');

        $images = $response->json('data.productImages');
        $this->assertCount(2, $images);
        $this->assertMatchesRegularExpression('/^\d{13}-\d+\.png$/', $images[0]);
        Storage::disk('uploads')->assertExists("products/{$images[0]}");
        Storage::disk('uploads')->assertExists("products/{$images[1]}");
    }

    public function test_create_validation_and_conflicts(): void
    {
        $category = Category::factory()->create();
        $headers = $this->asAdmin();
        $payload = ['productName' => 'X', 'sku' => 'X-1', 'price' => 10, 'qty' => 1, 'description' => 'd', 'categories' => [$category->id]];

        $this->postJson('/api/products', ['productName' => 'X'], $headers)
            ->assertStatus(400)->assertExactJson(['success' => false, 'message' => 'All required fields must be provided']);
        $this->postJson('/api/products', ['price' => 0] + $payload, $headers)->assertStatus(400);
        $this->postJson('/api/products', ['categories' => []] + $payload, $headers)->assertStatus(400);

        $this->postJson('/api/products', $payload, $headers)->assertCreated();
        $this->postJson('/api/products', $payload, $headers)
            ->assertStatus(409)->assertJsonPath('message', 'SKU already exists');

        $this->postJson('/api/products', ['sku' => 'X-2', 'price' => 'abc'] + $payload, $headers)
            ->assertStatus(500)->assertJsonPath('message', 'Error creating product');
        $this->postJson('/api/products', ['sku' => 'X-3', 'tax_status' => 'weird'] + $payload, $headers)
            ->assertStatus(500)->assertJsonPath('error', 'Product validation failed: tax_status: `weird` is not a valid enum value for path `tax_status`.');
    }

    public function test_upload_rules(): void
    {
        $category = Category::factory()->create();
        $fields = [['productName', 'F'], ['sku', 'F-1'], ['price', '1'], ['qty', '1'], ['description', 'd'], ['categories', $category->id]];

        $this->multipart('POST', '/api/products', $fields, [['productImages', 'x.txt', 'text/plain']], $this->asAdmin())
            ->assertStatus(500)
            ->assertExactJson(['message' => 'Something went wrong!', 'error' => 'Invalid file type. Only jpg, png, gif allowed.']);

        $this->multipart('POST', '/api/products', $fields, [['image', 'x.png', 'image/png']], $this->asAdmin())
            ->assertStatus(500)->assertJsonPath('error', 'Unexpected field');

        $response = $this->multipart('POST', '/api/products', $fields, [['productImages', 'shell.php', 'image/png']], $this->asAdmin());
        $this->assertStringEndsWith('.png', $response->assertCreated()->json('data.productImages.0'));
    }

    public function test_listing_shows_active_items_with_images_and_their_root_categories(): void
    {
        $root = Category::factory()->create(['name' => 'Makeup']);
        $child = Category::factory()->childOf($root)->create(['name' => 'Lips']);

        $listed = Product::factory()->create(['product_name' => 'Lipstick', 'price' => 20]);
        $listed->syncCategoriesInOrder([$child->id, $root->id]);
        Product::factory()->withoutImages()->create();
        Product::factory()->inactive()->create();
        Product::factory()->create(['product_name' => 'Cheap', 'price' => 5]);

        $this->getJson('/api/products?sort=price_low_high')
            ->assertOk()
            ->assertJsonPath('pagination', ['total' => 2, 'page' => 1, 'limit' => 10, 'pages' => 1])
            ->assertJsonPath('data.0.productName', 'Cheap')
            ->assertJsonPath('data.1.categories', [['_id' => $root->id, 'name' => 'Makeup', 'slug' => $root->slug]])
            ->assertJsonMissingPath('data.0.tax_status');

        $this->getJson('/api/products?search=makeup')->assertJsonCount(1, 'data');
        $this->getJson('/api/products?limit=0')->assertStatus(500)->assertJsonPath('message', 'Error fetching products');
    }

    public function test_details_expand_categories_and_related_items(): void
    {
        $root = Category::factory()->create(['name' => 'Makeup']);
        $child = Category::factory()->childOf($root)->create(['name' => 'Lips']);
        $product = Product::factory()->create();
        $product->syncCategoriesInOrder([$child->id, $root->id]);
        Product::factory()->count(5)->create();

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.categories.0.name', 'Lips')
            ->assertJsonPath('data.categories.1.name', 'Makeup');

        $this->getJson("/api/products/{$product->id}/related")->assertOk()->assertJsonCount(4, 'data');
        $this->getJson('/api/products/0123456789abcdef01234567')->assertNotFound()->assertJsonPath('message', 'Product not found');
    }

    public function test_malformed_ids_are_not_found(): void
    {
        foreach (['abc', 'été', "x' OR 1=1", str_repeat('z', 24)] as $id) {
            $this->getJson('/api/products/'.rawurlencode($id))->assertNotFound()->assertJsonPath('message', 'Product not found');
            $this->putJson('/api/products/'.rawurlencode($id), [], $this->asAdmin())->assertNotFound();
        }
    }

    public function test_multipart_put_merges_existing_and_new_images_and_resets_omitted_fields(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['slug' => 'old', 'tax_status' => 'taxable', 'product_images' => ['a.jpg', 'b.jpg']]);

        $response = $this->multipart('PUT', "/api/products/{$product->id}", [
            ['productName', 'Renamed'],
            ['existingImages', json_encode(['b.jpg'])],
            ['categories', json_encode([$category->id])],
        ], [['productImages', 'new.png', 'image/png']], $this->asAdmin());

        $response->assertOk()
            ->assertJsonPath('message', 'Product updated successfully')
            ->assertJsonPath('data.productName', 'Renamed')
            ->assertJsonPath('data.slug', '')
            ->assertJsonPath('data.tax_status', 'none')
            ->assertJsonPath('data.categories', [$category->id]);

        $images = $response->json('data.productImages');
        $this->assertSame('b.jpg', $images[0]);
        $this->assertCount(2, $images);

        $this->putJson('/api/products/0123456789abcdef01234567', [], $this->asAdmin())
            ->assertNotFound()->assertExactJson(['success' => false, 'message' => 'Product not found', 'data' => null]);
    }

    public function test_soft_delete_and_promo_codes(): void
    {
        $product = Product::factory()->create(['price' => 19.99]);

        $this->postJson('/api/products/apply-coupon', ['productId' => $product->id, 'couponCode' => 'save20'])
            ->assertOk()
            ->assertJsonPath('data', [
                'product' => $product->product_name,
                'originalPrice' => 19.99,
                'discount' => 20,
                'discountType' => 'percentage',
                'discountedPrice' => 15.99,
                'savings' => 4,
            ]);
        $this->postJson('/api/products/apply-coupon', ['productId' => $product->id, 'couponCode' => 'NOPE'])
            ->assertStatus(400)->assertJsonPath('message', 'Invalid coupon code');

        $this->deleteJson("/api/products/{$product->id}", [], $this->asAdmin())
            ->assertOk()->assertJsonPath('message', 'Product soft deleted (status set to inactive)');
        $this->assertSame('inactive', $product->fresh()->status);
        $this->getJson("/api/products/{$product->id}/related")->assertNotFound();
    }

    public function test_gift_cards_share_the_catalogue_behaviour_with_their_own_messages(): void
    {
        $category = Category::factory()->create();
        $headers = $this->asAdmin();

        $id = $this->postJson('/api/giftcards', [
            'productName' => 'Gift $50', 'sku' => 'G-1', 'price' => 50, 'qty' => 10, 'description' => 'Gift', 'categories' => [$category->id],
        ], $headers)->assertCreated()->assertJsonPath('message', 'GiftCard created successfully')->json('data._id');

        $this->assertTrue(GiftCard::query()->whereKey($id)->exists());
        $this->deleteJson("/api/giftcards/{$id}", [], $headers)->assertJsonPath('message', 'Gift Card deleted (status set to inactive)');
        $this->deleteJson('/api/giftcards/0123456789abcdef01234567', [], $headers)->assertNotFound()->assertJsonPath('message', 'Gift card not found');
    }
}
