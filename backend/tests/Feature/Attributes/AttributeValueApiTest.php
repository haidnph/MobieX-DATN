<?php

namespace Tests\Feature\Attributes;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttributeValueApiTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Public Read Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests can list active values in stable order without seeing hidden
     * values or values owned by another attribute.
     */
    public function test_guest_can_list_active_values_in_stable_order(): void
    {
        $attribute = Attribute::factory()->create([
            'type' => 'select',
            'status' => true,
        ]);

        $firstValue = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '8GB',
                'sort_order' => 1,
                'status' => true,
            ]);

        $secondValue = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '12GB',
                'sort_order' => 2,
                'status' => true,
            ]);

        $thirdValueAtSameOrder = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '16GB',
                'sort_order' => 2,
                'status' => true,
            ]);

        AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => 'Hidden value',
                'sort_order' => 0,
                'status' => false,
            ]);

        $otherAttribute = Attribute::factory()->create();

        AttributeValue::factory()
            ->for($otherAttribute)
            ->create([
                'value' => 'Other attribute value',
            ]);

        $response = $this->getJson(
            '/api/attributes/'.$attribute->getKey().'/values',
        );

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Attribute values retrieved successfully.',
            )
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $firstValue->getKey())
            ->assertJsonPath('data.1.id', $secondValue->getKey())
            ->assertJsonPath('data.2.id', $thirdValueAtSameOrder->getKey())
            ->assertJsonMissing([
                'value' => 'Hidden value',
            ])
            ->assertJsonMissing([
                'value' => 'Other attribute value',
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
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
            ]);
    }

    /**
     * A hidden parent attribute must not expose its values publicly.
     */
    public function test_public_value_list_returns_not_found_for_hidden_attribute(): void
    {
        $attribute = Attribute::factory()->create([
            'status' => false,
        ]);

        $this->getJson(
            '/api/attributes/'.$attribute->getKey().'/values',
        )->assertNotFound();
    }

    /**
     * A missing parent attribute returns 404.
     */
    public function test_public_value_list_returns_not_found_for_missing_attribute(): void
    {
        $this->getJson('/api/attributes/999999/values')
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Create Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests cannot create AttributeValues.
     */
    public function test_guest_cannot_create_attribute_value(): void
    {
        $attribute = Attribute::factory()->create();

        $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [
                'value' => '16GB',
            ],
        )->assertUnauthorized();

        $this->assertDatabaseCount('attribute_values', 0);
    }

    /**
     * Users without attributes.manage cannot create AttributeValues.
     */
    public function test_user_without_permission_cannot_create_attribute_value(): void
    {
        $attribute = Attribute::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [
                'value' => '16GB',
            ],
        )->assertForbidden();

        $this->assertDatabaseCount('attribute_values', 0);
    }

    /**
     * Inactive users cannot create values even when they have permission.
     */
    public function test_inactive_attribute_manager_cannot_create_attribute_value(): void
    {
        $attribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager([
            'is_active' => false,
        ]);

        $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [
                'value' => '16GB',
            ],
        )
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseCount('attribute_values', 0);
    }

    /**
     * An authorized manager can create a value and omitted optional fields
     * use the database defaults.
     */
    public function test_attribute_manager_can_create_value_with_database_defaults(): void
    {
        $attribute = Attribute::factory()->create([
            'type' => 'select',
        ]);

        $this->authenticateAsAttributeManager();

        $response = $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [
                'value' => '16GB',
            ],
        );

        $response->assertCreated()
            ->assertJsonPath(
                'message',
                'Attribute value created successfully.',
            )
            ->assertJsonPath('data.attribute_value.attribute_id', $attribute->getKey())
            ->assertJsonPath('data.attribute_value.value', '16GB')
            ->assertJsonPath('data.attribute_value.color_code', null)
            ->assertJsonPath('data.attribute_value.sort_order', 0)
            ->assertJsonPath('data.attribute_value.status', true)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'attribute_value' => [
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
            ]);

        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $attribute->getKey(),
            'value' => '16GB',
            'color_code' => null,
            'sort_order' => 0,
            'status' => true,
        ]);
    }

    /**
     * The value field is required when creating an AttributeValue.
     */
    public function test_creation_requires_value(): void
    {
        $attribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager();

        $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);

        $this->assertDatabaseCount('attribute_values', 0);
    }

    /**
     * Explicit null values are rejected for non-null database columns.
     */
    public function test_creation_rejects_explicit_null_sort_order_and_status(): void
    {
        $attribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager();

        $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [
                'value' => '16GB',
                'sort_order' => null,
                'status' => null,
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'sort_order',
                'status',
            ]);

        $this->assertDatabaseCount('attribute_values', 0);
    }

    /**
     * A nonexistent parent attribute returns 404 before value creation.
     */
    public function test_create_returns_not_found_for_missing_attribute(): void
    {
        $this->authenticateAsAttributeManager();

        $this->postJson('/api/attributes/999999/values', [
            'value' => '16GB',
        ])->assertNotFound();

        $this->assertDatabaseCount('attribute_values', 0);
    }

    /**
     * A color attribute accepts a valid hexadecimal color code.
     */
    public function test_attribute_manager_can_create_color_value_with_hex_code(): void
    {
        $attribute = Attribute::factory()->create([
            'type' => 'color',
        ]);

        $this->authenticateAsAttributeManager();

        $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [
                'value' => 'Midnight Black',
                'color_code' => '#1A2B3C',
            ],
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.attribute_value.color_code',
                '#1A2B3C',
            );

        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $attribute->getKey(),
            'value' => 'Midnight Black',
            'color_code' => '#1A2B3C',
        ]);
    }

    /**
     * Values may be reused under different attributes.
     */
    public function test_same_value_can_exist_under_different_attributes(): void
    {
        $firstAttribute = Attribute::factory()->create();
        $secondAttribute = Attribute::factory()->create();

        AttributeValue::factory()
            ->for($firstAttribute)
            ->create([
                'value' => 'Black',
            ]);

        $this->authenticateAsAttributeManager();

        $this->postJson(
            '/api/attributes/'.$secondAttribute->getKey().'/values',
            [
                'value' => 'Black',
            ],
        )->assertCreated();

        $this->assertDatabaseCount('attribute_values', 2);
    }

    /**
     * Duplicate values under the same attribute are rejected.
     */
    public function test_duplicate_value_within_same_attribute_is_rejected(): void
    {
        $attribute = Attribute::factory()->create();

        AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '16GB',
            ]);

        $this->authenticateAsAttributeManager();

        $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [
                'value' => '16GB',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);

        $this->assertDatabaseCount('attribute_values', 1);
    }

    /**
     * Creation validates required fields, constraints, unsupported fields,
     * and the prohibition on non-null color codes for non-color attributes.
     */
    public function test_creation_rejects_invalid_payload_and_non_color_code(): void
    {
        $attribute = Attribute::factory()->create([
            'type' => 'select',
        ]);

        $this->authenticateAsAttributeManager();

        $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [
                'value' => str_repeat('A', 256),
                'color_code' => '#fff',
                'sort_order' => -1,
                'status' => 'not-a-boolean',
                'attribute_id' => $attribute->getKey(),
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'value',
                'color_code',
                'sort_order',
                'status',
                'attribute_id',
            ]);

        $this->assertDatabaseCount('attribute_values', 0);
    }

    /**
     * Invalid hexadecimal values are rejected for color attributes.
     */
    public function test_color_attribute_rejects_invalid_hex_code(): void
    {
        $attribute = Attribute::factory()->create([
            'type' => 'color',
        ]);

        $this->authenticateAsAttributeManager();

        $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [
                'value' => 'Invalid color',
                'color_code' => 'not-a-hex-color',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color_code']);

        $this->assertDatabaseCount('attribute_values', 0);
    }

    /**
     * Hidden attributes remain manageable through authorized write routes.
     */
    public function test_attribute_manager_can_create_value_under_hidden_attribute(): void
    {
        $attribute = Attribute::factory()->create([
            'status' => false,
        ]);

        $this->authenticateAsAttributeManager();

        $this->postJson(
            '/api/attributes/'.$attribute->getKey().'/values',
            [
                'value' => 'Hidden parent value',
            ],
        )->assertCreated();

        $this->assertDatabaseHas('attribute_values', [
            'attribute_id' => $attribute->getKey(),
            'value' => 'Hidden parent value',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests cannot update AttributeValues.
     */
    public function test_guest_cannot_update_attribute_value(): void
    {
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create([
            'value' => '8GB',
        ]);

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
            [
                'value' => '16GB',
            ],
        )->assertUnauthorized();

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
            'value' => '8GB',
        ]);
    }

    /**
     * Users without permission cannot update AttributeValues.
     */
    public function test_user_without_permission_cannot_update_attribute_value(): void
    {
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create([
            'value' => '8GB',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
            [
                'value' => '16GB',
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
            'value' => '8GB',
        ]);
    }

    /**
     * Inactive users cannot update values even when they have permission.
     */
    public function test_inactive_attribute_manager_cannot_update_attribute_value(): void
    {
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create([
            'value' => '8GB',
        ]);

        $this->authenticateAsAttributeManager([
            'is_active' => false,
        ]);

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
            [
                'value' => '16GB',
            ],
        )
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
            'value' => '8GB',
        ]);
    }

    /**
     * An authorized manager can partially update a hidden value.
     */
    public function test_attribute_manager_can_partially_update_hidden_value(): void
    {
        $attribute = Attribute::factory()->create([
            'status' => false,
        ]);

        $value = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '8GB',
                'sort_order' => 1,
                'status' => false,
            ]);

        $this->authenticateAsAttributeManager();

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
            [
                'value' => '12GB',
                'status' => true,
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Attribute value updated successfully.',
            )
            ->assertJsonPath('data.attribute_value.id', $value->getKey())
            ->assertJsonPath('data.attribute_value.value', '12GB')
            ->assertJsonPath('data.attribute_value.sort_order', 1)
            ->assertJsonPath('data.attribute_value.status', true);

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
            'attribute_id' => $attribute->getKey(),
            'value' => '12GB',
            'sort_order' => 1,
            'status' => true,
        ]);
    }

    /**
     * An existing value may be retained, but another duplicate under the
     * same attribute is rejected.
     */
    public function test_update_ignores_current_value_for_uniqueness_but_rejects_other_duplicates(): void
    {
        $attribute = Attribute::factory()->create();

        $firstValue = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '8GB',
            ]);

        AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '16GB',
            ]);

        $this->authenticateAsAttributeManager();

        $url = '/api/attributes/'.$attribute->getKey()
            .'/values/'.$firstValue->getKey();

        $this->patchJson($url, [
            'value' => '8GB',
        ])->assertOk();

        $this->patchJson($url, [
            'value' => '16GB',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['value']);
    }

    /**
     * A color code can be updated when the parent attribute is a color type.
     */
    public function test_color_attribute_manager_can_update_hex_code(): void
    {
        $attribute = Attribute::factory()->create([
            'type' => 'color',
        ]);

        $value = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => 'Midnight Black',
                'color_code' => '#000000',
            ]);

        $this->authenticateAsAttributeManager();

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
            [
                'color_code' => '#abc',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.attribute_value.color_code', '#abc');

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
            'color_code' => '#abc',
        ]);
    }

    /**
     * Non-color attributes cannot be assigned a non-null color code.
     */
    public function test_update_rejects_non_null_color_code_for_non_color_attribute(): void
    {
        $attribute = Attribute::factory()->create([
            'type' => 'select',
        ]);

        $value = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '8GB',
                'color_code' => null,
            ]);

        $this->authenticateAsAttributeManager();

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
            [
                'color_code' => '#ffffff',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['color_code']);

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
            'color_code' => null,
        ]);
    }

    /**
     * PATCH rejects an empty body and unsupported fields.
     */
    public function test_update_rejects_empty_and_unsupported_payloads(): void
    {
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        $this->authenticateAsAttributeManager();

        $url = '/api/attributes/'.$attribute->getKey()
            .'/values/'.$value->getKey();

        $this->patchJson($url, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attribute_value']);

        $this->patchJson($url, [
            'attribute_id' => $attribute->getKey(),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute_id',
                'attribute_value',
            ]);
    }

    /**
     * A value belonging to another attribute cannot be updated through this
     * attribute's nested endpoint.
     */
    public function test_update_returns_not_found_when_value_belongs_to_another_attribute(): void
    {
        $parentAttribute = Attribute::factory()->create();
        $otherAttribute = Attribute::factory()->create();

        $value = AttributeValue::factory()
            ->for($otherAttribute)
            ->create();

        $this->authenticateAsAttributeManager();

        $this->patchJson(
            '/api/attributes/'.$parentAttribute->getKey().'/values/'.$value->getKey(),
            [
                'value' => 'Updated value',
            ],
        )->assertNotFound();

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
            'attribute_id' => $otherAttribute->getKey(),
        ]);
    }

    /**
     * An unknown value returns 404 on update.
     */
    public function test_update_returns_not_found_for_missing_value(): void
    {
        $attribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager();

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey().'/values/999999',
            [
                'value' => 'Updated value',
            ],
        )->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests cannot delete AttributeValues.
     */
    public function test_guest_cannot_delete_attribute_value(): void
    {
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
        )->assertUnauthorized();

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
        ]);
    }

    /**
     * Users without permission cannot delete AttributeValues.
     */
    public function test_user_without_permission_cannot_delete_attribute_value(): void
    {
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
        )->assertForbidden();

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
        ]);
    }

    /**
     * Inactive users cannot delete values even when they have permission.
     */
    public function test_inactive_attribute_manager_cannot_delete_attribute_value(): void
    {
        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        $this->authenticateAsAttributeManager([
            'is_active' => false,
        ]);

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
        )
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
        ]);
    }

    /**
     * An unreferenced value can be deleted before ProductVariant integration
     * has created its pivot table.
     */
    public function test_attribute_manager_can_delete_value_when_variant_pivot_is_absent(): void
    {
        $this->authenticateAsAttributeManager();

        $this->assertFalse(
            Schema::hasTable('variant_attribute_values'),
        );

        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
        )
            ->assertOk()
            ->assertJson([
                'message' => 'Attribute value deleted successfully.',
            ]);

        $this->assertDatabaseMissing('attribute_values', [
            'id' => $value->getKey(),
        ]);

        $this->assertFalse(
            Schema::hasTable('variant_attribute_values'),
        );
    }

    /**
     * A value referenced by a ProductVariant cannot be deleted.
     */
    public function test_attribute_value_cannot_be_deleted_while_referenced_by_variant(): void
    {
        $this->authenticateAsAttributeManager();

        $this->createVariantAttributeValuesTable();

        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        DB::table('variant_attribute_values')->insert([
            'variant_id' => 1,
            'attribute_value_id' => $value->getKey(),
        ]);

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Attribute value cannot be deleted while it is used by ProductVariants. Set status to false to hide the value instead.',
            ]);

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
        ]);

        $this->assertDatabaseHas('variant_attribute_values', [
            'attribute_value_id' => $value->getKey(),
        ]);
    }

    /**
     * An incomplete pivot table is unsafe for value deletion.
     */
    public function test_attribute_value_cannot_be_deleted_when_variant_pivot_lacks_value_column(): void
    {
        $this->authenticateAsAttributeManager();

        $this->createVariantAttributeValuesTable(
            includeValueColumn: false,
        );

        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Attribute value cannot be deleted safely because the variant_attribute_values.attribute_value_id reference is unavailable. Set status to false to hide the value instead.',
            ]);

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
        ]);
    }

    /**
     * A pivot without a restrictive foreign key cannot guarantee safe
     * deletion during concurrent ProductVariant writes.
     */
    public function test_attribute_value_cannot_be_deleted_without_restrictive_foreign_key(): void
    {
        $this->authenticateAsAttributeManager();

        $this->createVariantAttributeValuesTable(
            addForeignKey: false,
        );

        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Attribute value cannot be deleted safely because variant_attribute_values.attribute_value_id must reference attribute_values.id with ON DELETE RESTRICT or NO ACTION. Configure the restrictive foreign key in the ProductVariant schema, or set status to false to hide the value instead.',
            ]);

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
        ]);
    }

    /**
     * A cascading pivot foreign key is not accepted because deleting a value
     * must not silently remove a ProductVariant selection.
     */
    public function test_attribute_value_cannot_be_deleted_with_cascading_variant_foreign_key(): void
    {
        $this->authenticateAsAttributeManager();

        $this->createVariantAttributeValuesTable(
            onDelete: 'cascade',
        );

        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
        )->assertConflict();

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
        ]);
    }

    /**
     * An unreferenced value can be deleted when the pivot has a restrictive
     * foreign key to attribute_values.id.
     */
    public function test_attribute_manager_can_delete_unreferenced_value_with_restrictive_foreign_key(): void
    {
        $this->authenticateAsAttributeManager();

        $this->createVariantAttributeValuesTable();

        $attribute = Attribute::factory()->create();
        $value = AttributeValue::factory()->for($attribute)->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey().'/values/'.$value->getKey(),
        )
            ->assertOk()
            ->assertJson([
                'message' => 'Attribute value deleted successfully.',
            ]);

        $this->assertDatabaseMissing('attribute_values', [
            'id' => $value->getKey(),
        ]);
    }

    /**
     * A value belonging to another attribute cannot be deleted through this
     * attribute's nested endpoint.
     */
    public function test_delete_returns_not_found_when_value_belongs_to_another_attribute(): void
    {
        $parentAttribute = Attribute::factory()->create();
        $otherAttribute = Attribute::factory()->create();

        $value = AttributeValue::factory()
            ->for($otherAttribute)
            ->create();

        $this->authenticateAsAttributeManager();

        $this->deleteJson(
            '/api/attributes/'.$parentAttribute->getKey().'/values/'.$value->getKey(),
        )->assertNotFound();

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
            'attribute_id' => $otherAttribute->getKey(),
        ]);
    }

    /**
     * An unknown value returns 404 on deletion.
     */
    public function test_delete_returns_not_found_for_missing_value(): void
    {
        $attribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey().'/values/999999',
        )->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Test Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create an active user with attributes.manage permission.
     *
     * @param  array<string, mixed>  $userAttributes
     */
    private function authenticateAsAttributeManager(
        array $userAttributes = [],
    ): User {
        $user = User::factory()->create($userAttributes);

        $role = Role::factory()->create([
            'name' => 'Attribute Manager',
            'slug' => 'attribute-manager',
        ]);

        $permission = Permission::factory()->create([
            'name' => 'Manage Attributes',
            'slug' => 'attributes.manage',
        ]);

        $role->permissions()->attach(
            $permission->getKey(),
        );

        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Create the ProductVariant pivot table used by deletion safety tests.
     *
     * @param  string  $onDelete  restrict|cascade
     */
    private function createVariantAttributeValuesTable(
        bool $includeValueColumn = true,
        bool $addForeignKey = true,
        string $onDelete = 'restrict',
    ): void {
        Schema::create(
            'variant_attribute_values',
            function (Blueprint $table) use (
                $includeValueColumn,
                $addForeignKey,
                $onDelete,
            ): void {
                $table->unsignedBigInteger('variant_id');

                if ($includeValueColumn) {
                    $table->unsignedBigInteger('attribute_value_id');
                }

                $primaryColumns = ['variant_id'];

                if ($includeValueColumn) {
                    $primaryColumns[] = 'attribute_value_id';
                }

                $table->primary($primaryColumns);

                if (! $includeValueColumn || ! $addForeignKey) {
                    return;
                }

                $foreignKey = $table
                    ->foreign('attribute_value_id')
                    ->references('id')
                    ->on('attribute_values');

                if ($onDelete === 'restrict') {
                    $foreignKey->restrictOnDelete();
                } elseif ($onDelete === 'cascade') {
                    $foreignKey->cascadeOnDelete();
                }
            },
        );
    }
}
