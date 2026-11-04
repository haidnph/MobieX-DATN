<?php

namespace Tests\Feature\Attributes;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AttributeWriteTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Attribute Creation Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests cannot create attributes.
     */
    public function test_guest_cannot_create_attribute(): void
    {
        $this->postJson('/api/attributes', [
            'name' => 'RAM',
            'code' => 'ram',
            'type' => 'select',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('attributes', 0);
    }

    /**
     * Users without attributes.manage cannot create attributes.
     */
    public function test_user_without_permission_cannot_create_attribute(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/attributes', [
            'name' => 'RAM',
            'code' => 'ram',
            'type' => 'select',
        ])->assertForbidden();

        $this->assertDatabaseCount('attributes', 0);
    }

    /**
     * Inactive users cannot create attributes even when they have permission.
     */
    public function test_inactive_attribute_manager_cannot_create_attribute(): void
    {
        $this->authenticateAsAttributeManager([
            'is_active' => false,
        ]);

        $this->postJson('/api/attributes', [
            'name' => 'RAM',
            'code' => 'ram',
            'type' => 'select',
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseCount('attributes', 0);
    }

    /**
     * An authorized attribute manager can create an attribute.
     */
    public function test_attribute_manager_can_create_attribute(): void
    {
        $this->authenticateAsAttributeManager();

        $response = $this->postJson('/api/attributes', [
            'name' => 'RAM',
            'code' => 'ram',
            'type' => 'select',
            'sort_order' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath(
                'message',
                'Attribute created successfully.',
            )
            ->assertJsonPath('data.attribute.name', 'RAM')
            ->assertJsonPath('data.attribute.code', 'ram')
            ->assertJsonPath('data.attribute.type', 'select')
            ->assertJsonPath('data.attribute.sort_order', 1)
            ->assertJsonPath('data.attribute.status', true)
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
                    ],
                ],
            ]);

        $this->assertDatabaseHas('attributes', [
            'name' => 'RAM',
            'code' => 'ram',
            'type' => 'select',
            'sort_order' => 1,
            'status' => true,
        ]);

        $this->assertDatabaseCount('attributes', 1);
    }

    /**
     * Omitted optional fields use the database defaults.
     */
    public function test_attribute_creation_uses_database_defaults(): void
    {
        $this->authenticateAsAttributeManager();

        $this->postJson('/api/attributes', [
            'name' => 'Color',
            'code' => 'color',
            'type' => 'color',
        ])
            ->assertCreated()
            ->assertJsonPath('data.attribute.sort_order', 0)
            ->assertJsonPath('data.attribute.status', true);

        $this->assertDatabaseHas('attributes', [
            'code' => 'color',
            'sort_order' => 0,
            'status' => true,
        ]);
    }

    /**
     * Duplicate attribute codes are rejected.
     */
    public function test_attribute_creation_rejects_duplicate_code(): void
    {
        $this->authenticateAsAttributeManager();

        Attribute::factory()->create([
            'code' => 'ram',
        ]);

        $this->postJson('/api/attributes', [
            'name' => 'Random Access Memory',
            'code' => 'ram',
            'type' => 'select',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseCount('attributes', 1);
    }

    /**
     * Creation validates supported types, formats and field constraints.
     */
    public function test_attribute_creation_validates_payload(): void
    {
        $this->authenticateAsAttributeManager();

        $this->postJson('/api/attributes', [
            'name' => str_repeat('A', 101),
            'code' => 'Invalid-Code',
            'type' => 'number',
            'sort_order' => -1,
            'status' => 'not-a-boolean',
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'code',
                'type',
                'sort_order',
                'status',
                'is_active',
            ]);

        $this->assertDatabaseCount('attributes', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Attribute Update Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests cannot update attributes.
     */
    public function test_guest_cannot_update_attribute(): void
    {
        $attribute = Attribute::factory()->create([
            'name' => 'RAM',
            'code' => 'ram',
        ]);

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey(),
            [
                'name' => 'Memory',
            ],
        )->assertUnauthorized();

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
            'name' => 'RAM',
        ]);
    }

    /**
     * Users without attributes.manage cannot update attributes.
     */
    public function test_user_without_permission_cannot_update_attribute(): void
    {
        $attribute = Attribute::factory()->create([
            'name' => 'RAM',
            'code' => 'ram',
        ]);

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey(),
            [
                'name' => 'Memory',
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
            'name' => 'RAM',
        ]);
    }

    /**
     * Inactive users cannot update attributes.
     */
    public function test_inactive_attribute_manager_cannot_update_attribute(): void
    {
        $attribute = Attribute::factory()->create([
            'name' => 'RAM',
            'code' => 'ram',
        ]);

        $this->authenticateAsAttributeManager([
            'is_active' => false,
        ]);

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey(),
            [
                'name' => 'Memory',
            ],
        )
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
            'name' => 'RAM',
        ]);
    }

    /**
     * An authorized manager can update an attribute.
     */
    public function test_attribute_manager_can_update_attribute(): void
    {
        $this->authenticateAsAttributeManager();

        $attribute = Attribute::factory()->create([
            'name' => 'Old RAM',
            'code' => 'old_ram',
            'type' => 'select',
            'sort_order' => 1,
            'status' => true,
        ]);

        $response = $this->patchJson(
            '/api/attributes/'.$attribute->getKey(),
            [
                'name' => 'RAM',
                'code' => 'ram',
                'type' => 'select',
                'sort_order' => 2,
                'status' => true,
            ],
        );

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Attribute updated successfully.',
            )
            ->assertJsonPath(
                'data.attribute.id',
                $attribute->getKey(),
            )
            ->assertJsonPath('data.attribute.name', 'RAM')
            ->assertJsonPath('data.attribute.code', 'ram')
            ->assertJsonPath('data.attribute.type', 'select')
            ->assertJsonPath('data.attribute.sort_order', 2)
            ->assertJsonPath('data.attribute.status', true);

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
            'name' => 'RAM',
            'code' => 'ram',
            'sort_order' => 2,
        ]);
    }

    /**
     * PATCH updates only supplied fields.
     */
    public function test_partial_update_preserves_omitted_fields(): void
    {
        $this->authenticateAsAttributeManager();

        $attribute = Attribute::factory()->create([
            'name' => 'RAM',
            'code' => 'ram',
            'type' => 'select',
            'sort_order' => 5,
            'status' => true,
        ]);

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey(),
            [
                'sort_order' => 10,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.attribute.sort_order', 10)
            ->assertJsonPath('data.attribute.name', 'RAM')
            ->assertJsonPath('data.attribute.code', 'ram')
            ->assertJsonPath('data.attribute.type', 'select')
            ->assertJsonPath('data.attribute.status', true);
    }

    /**
     * Updating an attribute without changing its code is valid.
     */
    public function test_update_accepts_unchanged_code(): void
    {
        $this->authenticateAsAttributeManager();

        $attribute = Attribute::factory()->create([
            'code' => 'ram',
        ]);

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey(),
            [
                'code' => 'ram',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.attribute.code', 'ram');
    }

    /**
     * An attribute cannot use another attribute's code.
     */
    public function test_update_rejects_duplicate_code(): void
    {
        $this->authenticateAsAttributeManager();

        Attribute::factory()->create([
            'code' => 'ram',
        ]);

        $target = Attribute::factory()->create([
            'code' => 'storage',
        ]);

        $this->patchJson(
            '/api/attributes/'.$target->getKey(),
            [
                'code' => 'ram',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);

        $this->assertDatabaseHas('attributes', [
            'id' => $target->getKey(),
            'code' => 'storage',
        ]);
    }

    /**
     * Empty and unsupported PATCH payloads are rejected.
     */
    public function test_update_rejects_empty_and_unsupported_payloads(): void
    {
        $this->authenticateAsAttributeManager();

        $attribute = Attribute::factory()->create();

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey(),
            [],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attribute']);

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey(),
            [
                'is_active' => false,
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute',
                'is_active',
            ]);

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
            'status' => true,
        ]);
    }

    /**
     * Update validates every supplied field.
     */
    public function test_update_validates_fields(): void
    {
        $this->authenticateAsAttributeManager();

        $attribute = Attribute::factory()->create();

        $this->patchJson(
            '/api/attributes/'.$attribute->getKey(),
            [
                'name' => str_repeat('A', 101),
                'code' => 'Invalid-Code',
                'type' => 'number',
                'sort_order' => -1,
                'status' => 'not-a-boolean',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'code',
                'type',
                'sort_order',
                'status',
            ]);

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
        ]);
    }

    /**
     * A missing attribute returns 404 during update.
     */
    public function test_update_returns_not_found_for_missing_attribute(): void
    {
        $this->authenticateAsAttributeManager();

        $this->patchJson('/api/attributes/999999', [
            'name' => 'Updated Attribute',
        ])->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Attribute Deletion Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests cannot delete attributes.
     */
    public function test_guest_cannot_delete_attribute(): void
    {
        $attribute = Attribute::factory()->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey(),
        )->assertUnauthorized();

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
        ]);
    }

    /**
     * Users without attributes.manage cannot delete attributes.
     */
    public function test_user_without_permission_cannot_delete_attribute(): void
    {
        $attribute = Attribute::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey(),
        )->assertForbidden();

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
        ]);
    }

    /**
     * Inactive attribute managers cannot delete attributes.
     */
    public function test_inactive_attribute_manager_cannot_delete_attribute(): void
    {
        $this->authenticateAsAttributeManager([
            'is_active' => false,
        ]);

        $attribute = Attribute::factory()->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey(),
        )
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
        ]);
    }

    /**
     * An attribute can be deleted when ProductVariant integration
     * has not created its pivot table yet.
     *
     * Existing AttributeValue and category assignments must cascade.
     */
    public function test_attribute_manager_can_delete_attribute_when_variant_pivot_is_absent(): void
    {
        $this->authenticateAsAttributeManager();

        $this->assertFalse(
            Schema::hasTable('variant_attribute_values'),
        );

        $attribute = Attribute::factory()->create();

        $value = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '8GB',
            ]);

        $category = Category::factory()->create();

        $attribute->categories()->attach(
            $category->getKey(),
        );

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey(),
        )
            ->assertOk()
            ->assertJson([
                'message' => 'Attribute deleted successfully.',
            ]);

        $this->assertDatabaseMissing('attributes', [
            'id' => $attribute->getKey(),
        ]);

        $this->assertDatabaseMissing('attribute_values', [
            'id' => $value->getKey(),
        ]);

        $this->assertDatabaseMissing('attribute_category', [
            'attribute_id' => $attribute->getKey(),
            'category_id' => $category->getKey(),
        ]);

        $this->assertFalse(
            Schema::hasTable('variant_attribute_values'),
        );
    }

    /**
     * An attribute cannot be deleted while one of its values is
     * assigned to a ProductVariant.
     */
    public function test_attribute_cannot_be_deleted_while_values_are_referenced_by_variants(): void
    {
        $this->authenticateAsAttributeManager();

        $this->createVariantAttributeValuesTable();

        $attribute = Attribute::factory()->create();

        $value = AttributeValue::factory()
            ->for($attribute)
            ->create([
                'value' => '16GB',
            ]);

        DB::table('variant_attribute_values')->insert([
            'variant_id' => 1,
            'attribute_value_id' => $value->getKey(),
        ]);

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Attribute cannot be deleted while one or more attribute values are used by ProductVariants. Set status to false to hide the attribute instead.',
            ]);

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
        ]);

        $this->assertDatabaseHas('attribute_values', [
            'id' => $value->getKey(),
        ]);

        $this->assertDatabaseHas('variant_attribute_values', [
            'attribute_value_id' => $value->getKey(),
        ]);
    }

    /**
     * An existing pivot table without attribute_value_id cannot be
     * inspected safely. Deletion must fail closed.
     */
    public function test_attribute_cannot_be_deleted_when_variant_pivot_lacks_attribute_value_column(): void
    {
        $this->authenticateAsAttributeManager();

        Schema::create(
            'variant_attribute_values',
            function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('variant_id');
            },
        );

        $attribute = Attribute::factory()->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Attribute cannot be deleted safely because the variant_attribute_values.attribute_value_id reference is unavailable. Set status to false to hide the attribute instead.',
            ]);

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
        ]);
    }

    /**
     * A pivot table without a restrictive foreign key cannot guarantee
     * referential integrity during concurrent ProductVariant writes.
     */
    public function test_attribute_cannot_be_deleted_without_restrictive_attribute_value_foreign_key(): void
    {
        $this->authenticateAsAttributeManager();

        $this->createVariantAttributeValuesTable(
            addForeignKey: false,
        );

        $attribute = Attribute::factory()->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Attribute cannot be deleted safely because variant_attribute_values.attribute_value_id must reference attribute_values.id with ON DELETE RESTRICT or NO ACTION. Configure the restrictive foreign key in the ProductVariant schema, or set status to false to hide the attribute instead.',
            ]);

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
        ]);
    }

    /**
     * A cascading ProductVariant foreign key is not accepted.
     *
     * Deleting an attribute must not silently remove selected variant values.
     */
    public function test_attribute_cannot_be_deleted_with_cascading_variant_value_foreign_key(): void
    {
        $this->authenticateAsAttributeManager();

        $this->createVariantAttributeValuesTable(
            onDelete: 'cascade',
        );

        $attribute = Attribute::factory()->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey(),
        )
            ->assertConflict();

        $this->assertDatabaseHas('attributes', [
            'id' => $attribute->getKey(),
        ]);
    }

    /**
     * An unreferenced attribute can be deleted when the ProductVariant
     * pivot has a valid restrictive foreign key.
     */
    public function test_attribute_manager_can_delete_unreferenced_attribute_with_restrictive_foreign_key(): void
    {
        $this->authenticateAsAttributeManager();

        $this->createVariantAttributeValuesTable();

        $attribute = Attribute::factory()->create();

        $value = AttributeValue::factory()
            ->for($attribute)
            ->create();

        $this->deleteJson(
            '/api/attributes/'.$attribute->getKey(),
        )
            ->assertOk()
            ->assertJson([
                'message' => 'Attribute deleted successfully.',
            ]);

        $this->assertDatabaseMissing('attributes', [
            'id' => $attribute->getKey(),
        ]);

        $this->assertDatabaseMissing('attribute_values', [
            'id' => $value->getKey(),
        ]);
    }

    /**
     * A missing attribute returns 404 during deletion.
     */
    public function test_delete_returns_not_found_for_missing_attribute(): void
    {
        $this->authenticateAsAttributeManager();

        $this->deleteJson('/api/attributes/999999')
            ->assertNotFound();
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
     * Create a ProductVariant pivot table for deletion safety tests.
     *
     * @param  string  $onDelete  restrict|cascade
     */
    private function createVariantAttributeValuesTable(
        bool $addForeignKey = true,
        string $onDelete = 'restrict',
    ): void {
        Schema::create(
            'variant_attribute_values',
            function (Blueprint $table) use (
                $addForeignKey,
                $onDelete,
            ): void {
                $table->unsignedBigInteger('variant_id');

                $table->unsignedBigInteger('attribute_value_id');

                $table->primary([
                    'variant_id',
                    'attribute_value_id',
                ]);

                if (! $addForeignKey) {
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
