<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_roots_and_children_are_created_and_listed_as_a_tree(): void
    {
        $headers = $this->asAdmin();

        $rootId = $this->postJson('/api/category', ['name' => 'Skin', 'slug' => 'skin'], $headers)
            ->assertCreated()
            ->assertJsonPath('message', 'Category added successfully')
            ->assertJsonPath('data.parentId', null)
            ->json('data._id');

        $this->postJson('/api/category', ['name' => 'Serums', 'slug' => 'serums', 'parentId' => $rootId, 'count' => '5'], $headers)
            ->assertCreated()
            ->assertJsonPath('message', 'Child category added successfully')
            ->assertJsonPath('data.count', '5')
            ->assertJsonPath('data.image', null);

        $this->getJson('/api/category')
            ->assertOk()
            ->assertJsonPath('data.0.children.0.name', 'Serums')
            ->assertJsonPath('data.0.children.0.count', 5)
            ->assertJsonPath('pagination', ['total' => 1, 'page' => 1, 'limit' => 10, 'pages' => 1]);
    }

    public function test_only_root_categories_can_be_parents(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->childOf($root)->create();

        $this->postJson('/api/category', ['name' => 'X', 'slug' => 'x', 'parentId' => $child->id], $this->asAdmin())
            ->assertNotFound()->assertJsonPath('message', 'Parent category not found');
    }

    public function test_search_returns_matching_roots_with_matching_children_only(): void
    {
        $root = Category::factory()->create(['name' => 'Skin']);
        Category::factory()->childOf($root)->create(['name' => 'Serums']);
        Category::factory()->childOf($root)->create(['name' => 'Toners']);
        Category::factory()->create(['name' => 'Makeup']);

        $this->getJson('/api/category?search=ser')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Skin')
            ->assertJsonCount(1, 'data.0.children')
            ->assertJsonMissingPath('data.0.createdAt');
    }

    public function test_show_returns_roots_and_children_with_their_parent_id(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->childOf($root)->create();
        $grandchild = Category::factory()->childOf($child)->create();

        $this->getJson("/api/category/{$root->id}")->assertJsonPath('message', 'Category fetched successfully');
        $this->getJson("/api/category/{$child->id}")
            ->assertJsonPath('message', 'Child category fetched successfully')
            ->assertJsonPath('data.parentId', $root->id);
        $this->getJson("/api/category/{$grandchild->id}")->assertNotFound();
        $this->getJson('/api/category/abc')->assertStatus(400)->assertJsonPath('message', 'Invalid ID');
    }

    public function test_categories_move_between_parents_and_to_the_root(): void
    {
        $headers = $this->asAdmin();
        $skin = Category::factory()->create();
        $makeup = Category::factory()->create();
        $child = Category::factory()->childOf($skin)->create(['name' => 'Lips']);

        $this->putJson("/api/category/{$child->id}", ['parentId' => $makeup->id], $headers)
            ->assertOk()->assertJsonPath('message', 'Child category moved under new parent successfully');
        $this->assertSame($makeup->id, $child->fresh()->parent_id);

        $this->putJson("/api/category/{$child->id}", ['parentId' => 'none', 'name' => 'Lip Care'], $headers)
            ->assertOk()
            ->assertJsonPath('message', 'Child category moved to root successfully')
            ->assertJsonPath('data.name', 'Lip Care')
            ->assertJsonPath('data.parentId', null);

        $this->putJson("/api/category/{$skin->id}", ['parentId' => $makeup->id], $headers)
            ->assertOk()
            ->assertJsonPath('message', 'Category moved under new parent successfully')
            ->assertJsonPath('data.image', []);

        $this->putJson("/api/category/{$makeup->id}", ['name' => ''], $headers)
            ->assertStatus(500)->assertJsonPath('error', 'Category validation failed: name: Path `name` is required.');
    }

    public function test_deleting_a_category_removes_its_children_and_product_links(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->childOf($root)->create();
        $product = Product::factory()->create();
        $product->syncCategoriesInOrder([$child->id]);

        $this->deleteJson("/api/category/{$child->id}", [], $this->asAdmin())
            ->assertOk()->assertJsonPath('message', 'Child category deleted successfully');
        $this->deleteJson("/api/category/{$root->id}", [], $this->asAdmin())
            ->assertOk()->assertJsonPath('message', 'Category deleted successfully');

        $this->assertSame(0, Category::query()->count());
        $this->assertCount(0, $product->fresh()->categories);
    }
}
