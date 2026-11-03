<?php

namespace Tests\Feature\Attributes;

use App\Models\Attribute;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttributeCategorySchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_attribute_category_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('attribute_category', [
            'attribute_id',
            'category_id',
        ]));
    }

    public function test_attribute_can_belong_to_multiple_categories(): void
    {
        $attribute = Attribute::factory()->create();

        $firstCategory = Category::factory()->create();
        $secondCategory = Category::factory()->create();

        $attribute->categories()->attach([
            $firstCategory->id,
            $secondCategory->id,
        ]);

        $this->assertCount(2, $attribute->categories);
    }

    public function test_category_can_have_multiple_attributes(): void
    {
        $category = Category::factory()->create();

        $firstAttribute = Attribute::factory()->create();
        $secondAttribute = Attribute::factory()->create();

        $category->attributes()->attach([
            $firstAttribute->id,
            $secondAttribute->id,
        ]);

        $this->assertCount(2, $category->attributes);
    }

    public function test_duplicate_category_attribute_assignment_is_rejected(): void
    {
        $attribute = Attribute::factory()->create();
        $category = Category::factory()->create();

        $attribute->categories()->attach($category->id);

        $this->expectException(QueryException::class);

        $attribute->categories()->attach($category->id);
    }

    public function test_assignment_requires_an_existing_category(): void
    {
        $attribute = Attribute::factory()->create();

        $this->expectException(QueryException::class);

        $attribute->categories()->attach(999999);
    }

    public function test_assignment_requires_an_existing_attribute(): void
    {
        $category = Category::factory()->create();

        $this->expectException(QueryException::class);

        $category->attributes()->attach(999999);
    }

    public function test_deleting_category_cascades_to_attribute_assignments(): void
    {
        $attribute = Attribute::factory()->create();
        $category = Category::factory()->create();

        $attribute->categories()->attach($category->id);

        $category->delete();

        $this->assertDatabaseMissing('attribute_category', [
            'attribute_id' => $attribute->id,
            'category_id' => $category->id,
        ]);
    }
}
