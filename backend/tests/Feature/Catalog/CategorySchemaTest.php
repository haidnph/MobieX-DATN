<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategorySchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that the categories table has the expected columns.
     */
    public function test_categories_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('categories', [
            'id',
            'parent_id',
            'name',
            'slug',
            'description',
            'image',
            'sort_order',
            'status',
            'created_at',
            'updated_at',
        ]));
    }

    /**
     * Verify root and nested category relationships.
     */
    public function test_category_supports_root_and_nested_categories(): void
    {
        $parent = Category::factory()->create([
            'parent_id' => null,
        ]);

        $child = Category::factory()
            ->for($parent, 'parent')
            ->create();

        $this->assertNull($parent->parent_id);

        $this->assertSame(
            $parent->getKey(),
            $child->parent?->getKey(),
        );

        $this->assertTrue(
            $parent->children->contains('id', $child->getKey()),
        );
    }

    /**
     * Verify database defaults for status and sort_order.
     */
    public function test_category_has_correct_database_defaults(): void
    {
        $category = Category::query()->create([
            'name' => 'Mobile Phones',
            'slug' => 'mobile-phones',
        ]);

        $category->refresh();

        $this->assertTrue($category->status);

        $this->assertSame(0, $category->sort_order);

        $this->assertNull($category->parent_id);
    }

    /**
     * Verify category slug uniqueness.
     */
    public function test_category_slug_must_be_unique(): void
    {
        Category::factory()->create([
            'slug' => 'smart-phones',
        ]);

        $this->expectException(QueryException::class);

        Category::factory()->create([
            'slug' => 'smart-phones',
        ]);
    }

    /**
     * Verify the parent category must exist.
     */
    public function test_category_parent_must_reference_an_existing_category(): void
    {
        $this->expectException(QueryException::class);

        Category::factory()->create([
            'parent_id' => 999999,
        ]);
    }
}
