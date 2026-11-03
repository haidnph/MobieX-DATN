<?php

namespace Tests\Feature\Attributes;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttributeSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_attributes_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('attributes', [
            'id',
            'name',
            'code',
            'type',
            'sort_order',
            'status',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_attribute_uses_correct_database_defaults(): void
    {
        $attribute = Attribute::query()->create([
            'name' => 'RAM',
            'code' => 'ram',
        ]);

        $attribute->refresh();

        $this->assertSame('select', $attribute->type);
        $this->assertSame(0, $attribute->sort_order);
        $this->assertTrue($attribute->status);
    }

    public function test_attribute_factory_creates_a_valid_record(): void
    {
        $attribute = Attribute::factory()->create();

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->id,
            'code' => $attribute->code,
        ]);
    }

    public function test_attribute_code_must_be_unique(): void
    {
        Attribute::factory()->create([
            'code' => 'ram',
        ]);

        $this->expectException(QueryException::class);

        Attribute::factory()->create([
            'code' => 'ram',
        ]);
    }

    public function test_attribute_has_many_values(): void
    {
        $attribute = Attribute::factory()->create();

        $firstValue = AttributeValue::factory()->create([
            'attribute_id' => $attribute->id,
            'value' => '8GB',
        ]);

        $secondValue = AttributeValue::factory()->create([
            'attribute_id' => $attribute->id,
            'value' => '16GB',
        ]);

        $this->assertCount(2, $attribute->values);

        $this->assertTrue(
            $attribute->values->contains('id', $firstValue->id)
        );

        $this->assertTrue(
            $attribute->values->contains('id', $secondValue->id)
        );
    }

    public function test_deleting_attribute_cascades_to_values_and_category_assignments(): void
    {
        $attribute = Attribute::factory()->create();

        $value = AttributeValue::factory()->create([
            'attribute_id' => $attribute->id,
        ]);

        $category = Category::factory()->create();

        $attribute->categories()->attach($category->id);

        $attribute->delete();

        $this->assertDatabaseMissing('attribute_values', [
            'id' => $value->id,
        ]);

        $this->assertDatabaseMissing('attribute_category', [
            'attribute_id' => $attribute->id,
            'category_id' => $category->id,
        ]);
    }
}
