<?php

namespace Tests\Feature\Attributes;

use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttributeValueSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_attribute_values_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('attribute_values', [
            'id',
            'attribute_id',
            'value',
            'color_code',
            'sort_order',
            'status',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_attribute_value_uses_correct_database_defaults(): void
    {
        $attribute = Attribute::factory()->create();

        $value = AttributeValue::query()->create([
            'attribute_id' => $attribute->id,
            'value' => '16GB',
        ]);

        $value->refresh();

        $this->assertNull($value->color_code);
        $this->assertSame(0, $value->sort_order);
        $this->assertTrue($value->status);
    }

    public function test_attribute_value_factory_creates_a_valid_record(): void
    {
        $value = AttributeValue::factory()->create();

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->id,
            'attribute_id' => $value->attribute_id,
        ]);
    }

    public function test_attribute_value_must_be_unique_within_its_attribute(): void
    {
        $attribute = Attribute::factory()->create();

        AttributeValue::factory()->create([
            'attribute_id' => $attribute->id,
            'value' => '128GB',
        ]);

        $this->expectException(QueryException::class);

        AttributeValue::factory()->create([
            'attribute_id' => $attribute->id,
            'value' => '128GB',
        ]);
    }

    public function test_same_value_can_exist_under_different_attributes(): void
    {
        $ram = Attribute::factory()->create([
            'code' => 'ram',
        ]);

        $storage = Attribute::factory()->create([
            'code' => 'storage',
        ]);

        AttributeValue::factory()->create([
            'attribute_id' => $ram->id,
            'value' => '128GB',
        ]);

        AttributeValue::factory()->create([
            'attribute_id' => $storage->id,
            'value' => '128GB',
        ]);

        $this->assertDatabaseCount('attribute_values', 2);
    }

    public function test_attribute_value_requires_an_existing_attribute(): void
    {
        $this->expectException(QueryException::class);

        AttributeValue::factory()->create([
            'attribute_id' => 999999,
        ]);
    }

    public function test_attribute_value_preserves_product_variant_relationship_contract(): void
    {
        $relation = (new AttributeValue)->variants();

        $this->assertSame(
            'variant_attribute_values',
            $relation->getTable()
        );

        $this->assertSame(
            'attribute_value_id',
            $relation->getForeignPivotKeyName()
        );

        $this->assertSame(
            'variant_id',
            $relation->getRelatedPivotKeyName()
        );
    }
}
