<?php

namespace Tests\Feature\Attributes;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributeReadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests can list visible attributes using the default page size.
     *
     * Hidden attributes must not appear. Results are ordered by sort_order
     * and then ID so pagination remains deterministic.
     */
    public function test_guest_can_list_visible_attributes_with_pagination(): void
    {
        $lowestOrder = Attribute::factory()->create([
            'name' => 'RAM',
            'code' => 'ram',
            'sort_order' => 1,
            'status' => true,
        ]);

        $firstAtSameOrder = Attribute::factory()->create([
            'name' => 'Storage',
            'code' => 'storage',
            'sort_order' => 2,
            'status' => true,
        ]);

        $secondAtSameOrder = Attribute::factory()->create([
            'name' => 'Color',
            'code' => 'color',
            'sort_order' => 2,
            'status' => true,
        ]);

        foreach (range(1, 13) as $index) {
            Attribute::factory()->create([
                'name' => sprintf('Additional Attribute %02d', $index),
                'code' => sprintf('additional_%02d', $index),
                'sort_order' => 3,
                'status' => true,
            ]);
        }

        Attribute::factory()->create([
            'name' => 'Hidden Attribute',
            'code' => 'hidden_attribute',
            'status' => false,
        ]);

        $response = $this->getJson('/api/attributes');

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Attributes retrieved successfully.',
            )
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('data.0.id', $lowestOrder->getKey())
            ->assertJsonPath('data.1.id', $firstAtSameOrder->getKey())
            ->assertJsonPath('data.2.id', $secondAtSameOrder->getKey())
            ->assertJsonMissing([
                'code' => 'hidden_attribute',
            ])
            ->assertJsonMissingPath('data.0.values');

        $this->assertCount(15, $response->json('data'));
    }

    /**
     * The page query parameter selects the requested page.
     */
    public function test_guest_can_request_the_second_page_of_attributes(): void
    {
        $attributes = collect(range(1, 16))->map(
            static fn (int $index): Attribute => Attribute::factory()->create([
                'name' => sprintf('Attribute %02d', $index),
                'code' => sprintf('attribute_%02d', $index),
                'sort_order' => 1,
                'status' => true,
            ]),
        );

        $response = $this->getJson('/api/attributes?page=2');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('data.0.id', $attributes->last()->getKey());

        $this->assertCount(1, $response->json('data'));
    }

    /**
     * Category filtering returns only attributes directly assigned to it.
     */
    public function test_guest_can_filter_attributes_by_category_id(): void
    {
        $category = Category::factory()->create();
        $parent = Category::factory()->create();

        $child = Category::factory()
            ->for($parent, 'parent')
            ->create();

        $assignedAttribute = Attribute::factory()->create([
            'name' => 'RAM',
            'code' => 'ram',
            'status' => true,
        ]);

        $unassignedAttribute = Attribute::factory()->create([
            'name' => 'Storage',
            'code' => 'storage',
            'status' => true,
        ]);

        $hiddenAttribute = Attribute::factory()->create([
            'name' => 'Hidden Color',
            'code' => 'hidden_color',
            'status' => false,
        ]);

        $category->attributes()->attach($assignedAttribute->getKey());
        $category->attributes()->attach($hiddenAttribute->getKey());
        $parent->attributes()->attach($unassignedAttribute->getKey());

        $response = $this->getJson(
            '/api/attributes?category_id='.$category->getKey(),
        );

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $assignedAttribute->getKey())
            ->assertJsonMissing([
                'code' => 'storage',
            ])
            ->assertJsonMissing([
                'code' => 'hidden_color',
            ]);

        $this->getJson('/api/attributes?category_id='.$child->getKey())
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    /**
     * Type filtering returns only visible attributes of the requested type.
     */
    public function test_guest_can_filter_attributes_by_type(): void
    {
        Attribute::factory()->create([
            'name' => 'RAM',
            'code' => 'ram',
            'type' => 'select',
            'status' => true,
        ]);

        $colorAttribute = Attribute::factory()->create([
            'name' => 'Color',
            'code' => 'color',
            'type' => 'color',
            'status' => true,
        ]);

        Attribute::factory()->create([
            'name' => 'Hidden Color',
            'code' => 'hidden_color',
            'type' => 'color',
            'status' => false,
        ]);

        $this->getJson('/api/attributes?type=color')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $colorAttribute->getKey());
    }

    /**
     * Unsupported filter values return the standard JSON validation response.
     */
    public function test_invalid_attribute_filters_are_rejected(): void
    {
        $this->getJson('/api/attributes?category_id=invalid')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);

        $this->getJson('/api/attributes?type=number')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    /**
     * Guests can retrieve a visible attribute and its visible values.
     *
     * Nested values are ordered by sort_order and then ID. Hidden values
     * are excluded from the public detail response.
     */
    public function test_guest_can_retrieve_visible_attribute_details_with_visible_values(): void
    {
        $attribute = Attribute::factory()->create([
            'name' => 'RAM',
            'code' => 'ram',
            'type' => 'select',
            'sort_order' => 1,
            'status' => true,
        ]);

        $secondValue = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '16GB',
                'sort_order' => 2,
                'status' => true,
            ]);

        $firstValue = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '8GB',
                'sort_order' => 1,
                'status' => true,
            ]);

        AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => 'Hidden value',
                'sort_order' => 0,
                'status' => false,
            ]);

        $this->getJson('/api/attributes/'.$attribute->getKey())
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Attribute retrieved successfully.',
            )
            ->assertJsonPath('data.attribute.id', $attribute->getKey())
            ->assertJsonPath('data.attribute.name', 'RAM')
            ->assertJsonPath('data.attribute.code', 'ram')
            ->assertJsonPath('data.attribute.type', 'select')
            ->assertJsonCount(2, 'data.attribute.values')
            ->assertJsonPath(
                'data.attribute.values.0.id',
                $firstValue->getKey(),
            )
            ->assertJsonPath(
                'data.attribute.values.1.id',
                $secondValue->getKey(),
            )
            ->assertJsonMissing([
                'value' => 'Hidden value',
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'attribute' => [
                        'id',
                        'name',
                        'code',
                        'type',
                        'sort_order',
                        'status',
                        'created_at',
                        'updated_at',
                        'values' => [
                            '*' => [
                                'id',
                                'attribute_id',
                                'value',
                                'color_code',
                                'sort_order',
                                'status',
                                'created_at',
                                'updated_at',
                            ],
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Hidden attributes must not be exposed by the public detail endpoint.
     */
    public function test_hidden_attribute_returns_not_found(): void
    {
        $attribute = Attribute::factory()->create([
            'code' => 'hidden_attribute',
            'status' => false,
        ]);

        $this->getJson('/api/attributes/'.$attribute->getKey())
            ->assertNotFound();
    }

    /**
     * A nonexistent attribute must return 404.
     */
    public function test_missing_attribute_returns_not_found(): void
    {
        $this->getJson('/api/attributes/999999')
            ->assertNotFound();
    }
}
