<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\GiftCard;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogCategoryFilterTest extends TestCase
{
    use RefreshDatabase;

    private Category $comfortZone;

    private Category $activePureness;

    private Category $remedy;

    private Category $janeIredale;

    private Category $brows;

    private Category $giftCard;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->subMinute());
        $this->comfortZone = Category::factory()->create(['name' => 'Comfort zone']);
        $this->travel(1)->seconds();
        $this->janeIredale = Category::factory()->create(['name' => 'Jane Iredale']);
        $this->travel(1)->seconds();
        $this->giftCard = Category::factory()->create(['name' => 'Gift Card']);
        $this->travel(1)->seconds();
        Category::factory()->create(['name' => 'Uncategorized']);
        $this->travelBack();

        $this->activePureness = Category::factory()->childOf($this->comfortZone)->create(['name' => 'Active Pureness']);
        $this->remedy = Category::factory()->childOf($this->comfortZone)->create(['name' => 'Remedy']);
        $this->brows = Category::factory()->childOf($this->janeIredale)->create(['name' => 'Brows']);
        Category::factory()->childOf($this->janeIredale)->create(['name' => 'Concealers']);
    }

    public function test_the_sidebar_lists_only_categories_with_items_and_counts_them(): void
    {
        $this->product('Corrector', [$this->activePureness]);
        $this->product('Toner', [$this->activePureness, $this->comfortZone]);
        $this->product('Remedy Cream', [$this->remedy]);
        $this->product('Brow Gel', [$this->brows]);
        Product::factory()->inactive()->create()->syncCategoriesInOrder([$this->brows->id]);
        Product::factory()->withoutImages()->create()->syncCategoriesInOrder([$this->brows->id]);
        $this->giftCardIn([$this->giftCard]);

        $this->getJson('/api/products/categories')
            ->assertOk()
            ->assertExactJson([
                'success' => true,
                'data' => [
                    $this->node($this->comfortZone, 3, [$this->node($this->activePureness, 2), $this->node($this->remedy, 1)]),
                    $this->node($this->janeIredale, 1, [$this->node($this->brows, 1)]),
                ],
            ]);

        $this->getJson('/api/giftcards/categories')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => [$this->node($this->giftCard, 1)]]);
    }

    public function test_items_are_filtered_by_a_category_and_its_sub_categories(): void
    {
        $this->product('Corrector', [$this->activePureness]);
        $this->product('Toner', [$this->activePureness, $this->comfortZone]);
        $this->product('Remedy Cream', [$this->remedy]);
        $this->product('Brow Gel', [$this->brows]);

        $this->assertListed("/api/products?category={$this->comfortZone->id}", ['Corrector', 'Remedy Cream', 'Toner']);
        $this->assertListed("/api/products?category={$this->activePureness->id}", ['Corrector', 'Toner']);
        $this->assertListed("/api/products?category={$this->janeIredale->id}", ['Brow Gel']);
        $this->assertListed('/api/products', ['Brow Gel', 'Corrector', 'Remedy Cream', 'Toner']);

        $this->getJson("/api/products?category={$this->comfortZone->id}&page=1&limit=2&sort=price_low_high")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('pagination', ['total' => 3, 'page' => 1, 'limit' => 2, 'pages' => 2]);

        $this->getJson("/api/products?category={$this->comfortZone->id}&search=toner")
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.productName', 'Toner');
    }

    public function test_empty_unknown_or_malformed_categories_list_nothing(): void
    {
        $this->product('Brow Gel', [$this->brows]);
        $this->giftCardIn([$this->giftCard]);
        $empty = ['total' => 0, 'page' => 1, 'limit' => 10, 'pages' => 0];

        foreach ([$this->giftCard->id, '0123456789abcdef01234567', 'not-an-id', 'é'] as $category) {
            $this->getJson('/api/products?category='.urlencode($category))
                ->assertOk()->assertJsonPath('data', [])->assertJsonPath('pagination', $empty);
        }

        // Gift cards are filtered against their own category links.
        $this->getJson("/api/giftcards?category={$this->brows->id}")->assertOk()->assertJsonPath('data', []);
        $this->getJson("/api/giftcards?category={$this->giftCard->id}")->assertOk()->assertJsonCount(1, 'data');

        // An empty value means no filter.
        $this->getJson('/api/products?category=')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_categories_route_does_not_shadow_item_details(): void
    {
        $id = $this->product('Brow Gel', [$this->brows])->id;

        $this->getJson("/api/products/{$id}")->assertOk()->assertJsonPath('data.productName', 'Brow Gel');
        $this->getJson("/api/products/{$id}/related")->assertOk();
    }

    /**
     * @param  list<Category>  $categories
     */
    private function product(string $name, array $categories): Product
    {
        $product = Product::factory()->create(['product_name' => $name]);
        $product->syncCategoriesInOrder(array_map(fn (Category $category) => $category->id, $categories));

        return $product;
    }

    /**
     * @param  list<Category>  $categories
     */
    private function giftCardIn(array $categories): GiftCard
    {
        $giftCard = GiftCard::factory()->create();
        $giftCard->syncCategoriesInOrder(array_map(fn (Category $category) => $category->id, $categories));

        return $giftCard;
    }

    /**
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function node(Category $category, int $count, array $children = []): array
    {
        return ['_id' => $category->id, 'name' => $category->name, 'slug' => $category->slug, 'count' => $count, 'children' => $children];
    }

    /**
     * @param  list<string>  $names
     */
    private function assertListed(string $uri, array $names): void
    {
        $listed = $this->getJson($uri)->assertOk()->json('data.*.productName');
        sort($listed);

        $this->assertSame($names, $listed, $uri);
    }
}
