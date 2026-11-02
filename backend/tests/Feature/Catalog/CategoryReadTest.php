<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryReadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests can retrieve a paginated list of visible categories.
     *
     * Hidden categories must not appear in the result.
     * Records must be ordered by sort_order and then id.
     */
    public function test_guest_can_list_visible_categories_with_pagination(): void
    {
        $firstAtSecondOrder = Category::factory()->create([
            'name' => 'First Category',
            'slug' => 'first-category',
            'sort_order' => 2,
            'status' => true,
        ]);

        $firstAtFirstOrder = Category::factory()->create([
            'name' => 'Second Category',
            'slug' => 'second-category',
            'sort_order' => 1,
            'status' => true,
        ]);

        $secondAtSecondOrder = Category::factory()->create([
            'name' => 'Third Category',
            'slug' => 'third-category',
            'sort_order' => 2,
            'status' => true,
        ]);

        Category::factory()
            ->count(13)
            ->create([
                'sort_order' => 3,
                'status' => true,
            ]);

        Category::factory()->create([
            'name' => 'Hidden Category',
            'slug' => 'hidden-category',
            'status' => false,
        ]);

        $response = $this->getJson('/api/categories');

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Categories retrieved successfully.',
            )
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath(
                'data.0.id',
                $firstAtFirstOrder->getKey(),
            )
            ->assertJsonPath(
                'data.1.id',
                $firstAtSecondOrder->getKey(),
            )
            ->assertJsonPath(
                'data.2.id',
                $secondAtSecondOrder->getKey(),
            )
            ->assertJsonMissing([
                'slug' => 'hidden-category',
            ])
            ->assertJsonMissingPath('data.0.children');

        $this->assertCount(
            15,
            $response->json('data'),
        );
    }

    /**
     * Guests can retrieve a complete multi-level category tree.
     */
    public function test_guest_can_retrieve_multi_level_category_tree(): void
    {
        $root = Category::factory()->create([
            'name' => 'Phones',
            'slug' => 'phones',
            'parent_id' => null,
            'sort_order' => 1,
            'status' => true,
        ]);

        $child = Category::factory()
            ->for($root, 'parent')
            ->create([
                'name' => 'Smartphones',
                'slug' => 'smartphones',
                'sort_order' => 1,
                'status' => true,
            ]);

        $grandchild = Category::factory()
            ->for($child, 'parent')
            ->create([
                'name' => 'Android Phones',
                'slug' => 'android-phones',
                'sort_order' => 1,
                'status' => true,
            ]);

        Category::factory()
            ->for($root, 'parent')
            ->create([
                'name' => 'Hidden Child',
                'slug' => 'hidden-child',
                'status' => false,
            ]);

        $hiddenRoot = Category::factory()->create([
            'name' => 'Hidden Root',
            'slug' => 'hidden-root',
            'parent_id' => null,
            'status' => false,
        ]);

        Category::factory()
            ->for($hiddenRoot, 'parent')
            ->create([
                'name' => 'Child Of Hidden Root',
                'slug' => 'child-of-hidden-root',
                'status' => true,
            ]);

        $response = $this->getJson('/api/categories?tree=true');

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Categories retrieved successfully.',
            )
            ->assertJsonCount(1, 'data')
            ->assertJsonPath(
                'data.0.id',
                $root->getKey(),
            )
            ->assertJsonPath(
                'data.0.children.0.id',
                $child->getKey(),
            )
            ->assertJsonPath(
                'data.0.children.0.children.0.id',
                $grandchild->getKey(),
            )
            ->assertJsonMissing([
                'slug' => 'hidden-child',
            ])
            ->assertJsonMissing([
                'slug' => 'hidden-root',
            ])
            ->assertJsonMissing([
                'slug' => 'child-of-hidden-root',
            ])
            ->assertJsonMissingPath('meta');
    }

    /**
     * Guests can retrieve a visible category by ID.
     */
    public function test_guest_can_retrieve_visible_category_details(): void
    {
        $category = Category::factory()->create([
            'name' => 'Tablets',
            'slug' => 'tablets',
            'status' => true,
        ]);

        $this->getJson(
            '/api/categories/'.$category->getKey(),
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Category retrieved successfully.',
            )
            ->assertJsonPath(
                'data.category.id',
                $category->getKey(),
            )
            ->assertJsonPath(
                'data.category.name',
                'Tablets',
            )
            ->assertJsonPath(
                'data.category.slug',
                'tablets',
            )
            ->assertJsonPath(
                'data.category.status',
                true,
            )
            ->assertJsonMissingPath('data.category.children');
    }

    /**
     * Hidden categories must not be exposed by the detail endpoint.
     */
    public function test_hidden_category_returns_not_found(): void
    {
        $category = Category::factory()->create([
            'slug' => 'hidden-category',
            'status' => false,
        ]);

        $this->getJson(
            '/api/categories/'.$category->getKey(),
        )->assertNotFound();
    }

    /**
     * A nonexistent category must return 404.
     */
    public function test_missing_category_returns_not_found(): void
    {
        $this->getJson('/api/categories/999999')
            ->assertNotFound();
    }
}
