<?php

namespace Tests\Feature\Attributes;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryAttributeAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Public Read Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests can retrieve active attributes assigned directly to a category.
     *
     * Results must be ordered by sort_order ASC and then id ASC.
     * Hidden and unassigned attributes must not be included.
     */
    public function test_guest_can_list_active_attributes_assigned_directly_to_category(): void
    {
        $category = Category::factory()->create();

        $firstAttribute = Attribute::factory()->create([
            'sort_order' => 1,
            'status' => true,
        ]);

        $secondAttribute = Attribute::factory()->create([
            'sort_order' => 2,
            'status' => true,
        ]);

        $thirdAttribute = Attribute::factory()->create([
            'sort_order' => 2,
            'status' => true,
        ]);

        $hiddenAttribute = Attribute::factory()->create([
            'sort_order' => 0,
            'status' => false,
        ]);

        $unassignedAttribute = Attribute::factory()->create([
            'status' => true,
        ]);

        $category->attributes()->attach([
            $firstAttribute->getKey(),
            $secondAttribute->getKey(),
            $thirdAttribute->getKey(),
            $hiddenAttribute->getKey(),
        ]);

        $response = $this->getJson(
            '/api/categories/'.$category->getKey().'/attributes',
        );

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Category attributes retrieved successfully.',
            )
            ->assertJsonCount(3, 'data.attributes')
            ->assertJsonPath(
                'data.attributes.0.id',
                $firstAttribute->getKey(),
            )
            ->assertJsonPath(
                'data.attributes.1.id',
                $secondAttribute->getKey(),
            )
            ->assertJsonPath(
                'data.attributes.2.id',
                $thirdAttribute->getKey(),
            )
            ->assertJsonMissing([
                'id' => $hiddenAttribute->getKey(),
            ])
            ->assertJsonMissing([
                'id' => $unassignedAttribute->getKey(),
            ])
            ->assertJsonStructure([
                'message',
                'data' => [
                    'attributes' => [
                        '*' => [
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
                ],
            ]);
    }

    /**
     * A category without direct assignments returns an empty collection.
     */
    public function test_guest_receives_empty_collection_when_category_has_no_assignments(): void
    {
        $category = Category::factory()->create();

        $this->getJson(
            '/api/categories/'.$category->getKey().'/attributes',
        )
            ->assertOk()
            ->assertJsonPath('data.attributes', []);
    }

    /**
     * Public requests cannot retrieve assignments of hidden categories.
     */
    public function test_public_list_returns_not_found_for_hidden_category(): void
    {
        $category = Category::factory()->create([
            'status' => false,
        ]);

        Attribute::factory()->create()
            ->categories()
            ->attach($category->getKey());

        $this->getJson(
            '/api/categories/'.$category->getKey().'/attributes',
        )->assertNotFound();
    }

    /**
     * A nonexistent category returns 404.
     */
    public function test_public_list_returns_not_found_for_missing_category(): void
    {
        $this->getJson('/api/categories/999999/attributes')
            ->assertNotFound();
    }

    /**
     * Child categories do not inherit parent attribute assignments.
     */
    public function test_child_category_does_not_inherit_parent_attributes(): void
    {
        $parent = Category::factory()->create();

        $child = Category::factory()
            ->for($parent, 'parent')
            ->create();

        $parentAttribute = Attribute::factory()->create();

        $parent->attributes()->attach(
            $parentAttribute->getKey(),
        );

        $response = $this->getJson(
            '/api/categories/'.$child->getKey().'/attributes',
        );

        $response->assertOk()
            ->assertJsonPath('data.attributes', []);

        $this->assertDatabaseHas('attribute_category', [
            'category_id' => $parent->getKey(),
            'attribute_id' => $parentAttribute->getKey(),
        ]);

        $this->assertDatabaseMissing('attribute_category', [
            'category_id' => $child->getKey(),
            'attribute_id' => $parentAttribute->getKey(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests cannot replace category attribute assignments.
     */
    public function test_guest_cannot_replace_category_attributes(): void
    {
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create();

        $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [
                    $attribute->getKey(),
                ],
            ],
        )->assertUnauthorized();

        $this->assertDatabaseMissing('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $attribute->getKey(),
        ]);
    }

    /**
     * Authenticated users without attributes.manage receive HTTP 403.
     */
    public function test_user_without_attribute_permission_cannot_replace_assignments(): void
    {
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create();

        Sanctum::actingAs(
            User::factory()->create(),
        );

        $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [
                    $attribute->getKey(),
                ],
            ],
        )->assertForbidden();

        $this->assertDatabaseMissing('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $attribute->getKey(),
        ]);
    }

    /**
     * Inactive users cannot replace assignments even with permission.
     */
    public function test_inactive_attribute_manager_cannot_replace_assignments(): void
    {
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager([
            'is_active' => false,
        ]);

        $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [
                    $attribute->getKey(),
                ],
            ],
        )
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseMissing('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $attribute->getKey(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PUT Replacement Tests
    |--------------------------------------------------------------------------
    */

    /**
     * An authorized attribute manager can replace all existing assignments.
     */
    public function test_attribute_manager_can_replace_category_assignments(): void
    {
        $category = Category::factory()->create();

        $oldAttribute = Attribute::factory()->create([
            'sort_order' => 0,
        ]);

        $firstAttribute = Attribute::factory()->create([
            'sort_order' => 1,
        ]);

        $secondAttribute = Attribute::factory()->create([
            'sort_order' => 2,
        ]);

        $category->attributes()->attach(
            $oldAttribute->getKey(),
        );

        $this->authenticateAsAttributeManager();

        $response = $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [
                    $secondAttribute->getKey(),
                    $firstAttribute->getKey(),
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Category attributes updated successfully.',
            )
            ->assertJsonCount(2, 'data.attributes')
            ->assertJsonPath(
                'data.attributes.0.id',
                $firstAttribute->getKey(),
            )
            ->assertJsonPath(
                'data.attributes.1.id',
                $secondAttribute->getKey(),
            );

        $this->assertDatabaseMissing('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $oldAttribute->getKey(),
        ]);

        $this->assertDatabaseHas('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $firstAttribute->getKey(),
        ]);

        $this->assertDatabaseHas('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $secondAttribute->getKey(),
        ]);

        $this->assertDatabaseCount('attribute_category', 2);
    }

    /**
     * Existing hidden attributes may be assigned by an authorized manager.
     *
     * Hidden attributes are returned by PUT but remain excluded from public
     * GET responses.
     */
    public function test_attribute_manager_can_assign_hidden_attribute(): void
    {
        $category = Category::factory()->create();

        $hiddenAttribute = Attribute::factory()->create([
            'status' => false,
        ]);

        $this->authenticateAsAttributeManager();

        $response = $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [
                    $hiddenAttribute->getKey(),
                ],
            ],
        );

        $response->assertOk()
            ->assertJsonCount(1, 'data.attributes')
            ->assertJsonPath(
                'data.attributes.0.id',
                $hiddenAttribute->getKey(),
            )
            ->assertJsonPath(
                'data.attributes.0.status',
                false,
            );

        $this->assertDatabaseHas('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $hiddenAttribute->getKey(),
        ]);

        $this->getJson(
            '/api/categories/'.$category->getKey().'/attributes',
        )
            ->assertOk()
            ->assertJsonPath('data.attributes', []);
    }

    /**
     * An empty attribute_ids list removes all existing assignments.
     */
    public function test_empty_attribute_ids_removes_all_assignments(): void
    {
        $category = Category::factory()->create();

        $firstAttribute = Attribute::factory()->create();
        $secondAttribute = Attribute::factory()->create();

        $category->attributes()->attach([
            $firstAttribute->getKey(),
            $secondAttribute->getKey(),
        ]);

        $this->authenticateAsAttributeManager();

        $response = $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [],
            ],
        );

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Category attributes updated successfully.',
            )
            ->assertJsonPath('data.attributes', []);

        $this->assertDatabaseMissing('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $firstAttribute->getKey(),
        ]);

        $this->assertDatabaseMissing('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $secondAttribute->getKey(),
        ]);

        $this->assertDatabaseCount('attribute_category', 0);
    }

    /**
     * Repeating the same PUT request is idempotent.
     */
    public function test_repeating_same_assignment_is_idempotent(): void
    {
        $category = Category::factory()->create();

        $firstAttribute = Attribute::factory()->create();
        $secondAttribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager();

        $url = '/api/categories/'.$category->getKey().'/attributes';

        $payload = [
            'attribute_ids' => [
                $firstAttribute->getKey(),
                $secondAttribute->getKey(),
            ],
        ];

        $this->putJson($url, $payload)->assertOk();

        $this->putJson($url, $payload)
            ->assertOk()
            ->assertJsonCount(2, 'data.attributes');

        $this->assertDatabaseCount('attribute_category', 2);
    }

    /**
     * Assignments to hidden categories remain available to authorized
     * administrators through the category ID.
     */
    public function test_attribute_manager_can_replace_assignments_of_hidden_category(): void
    {
        $category = Category::factory()->create([
            'status' => false,
        ]);

        $attribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager();

        $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [
                    $attribute->getKey(),
                ],
            ],
        )
            ->assertOk()
            ->assertJsonCount(1, 'data.attributes');

        $this->assertDatabaseHas('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $attribute->getKey(),
        ]);

        $this->getJson(
            '/api/categories/'.$category->getKey().'/attributes',
        )->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Duplicate attribute IDs are rejected without changing existing
     * assignments.
     */
    public function test_duplicate_attribute_ids_are_rejected(): void
    {
        $category = Category::factory()->create();

        $existingAttribute = Attribute::factory()->create();
        $newAttribute = Attribute::factory()->create();

        $category->attributes()->attach(
            $existingAttribute->getKey(),
        );

        $this->authenticateAsAttributeManager();

        $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [
                    $newAttribute->getKey(),
                    $newAttribute->getKey(),
                ],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute_ids.1',
            ]);

        $this->assertDatabaseHas('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $existingAttribute->getKey(),
        ]);

        $this->assertDatabaseMissing('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $newAttribute->getKey(),
        ]);

        $this->assertDatabaseCount('attribute_category', 1);
    }

    /**
     * Nonexistent attribute IDs are rejected without partially replacing
     * the existing assignments.
     */
    public function test_nonexistent_attribute_id_is_rejected(): void
    {
        $category = Category::factory()->create();

        $existingAttribute = Attribute::factory()->create();

        $category->attributes()->attach(
            $existingAttribute->getKey(),
        );

        $this->authenticateAsAttributeManager();

        $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [
                    999999,
                ],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute_ids.0',
            ]);

        $this->assertDatabaseHas('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $existingAttribute->getKey(),
        ]);

        $this->assertDatabaseCount('attribute_category', 1);
    }

    /**
     * attribute_ids must be provided.
     */
    public function test_attribute_ids_field_is_required(): void
    {
        $category = Category::factory()->create();

        $this->authenticateAsAttributeManager();

        $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute_ids',
            ]);
    }

    /**
     * attribute_ids must be an array.
     */
    public function test_attribute_ids_must_be_an_array(): void
    {
        $category = Category::factory()->create();

        $this->authenticateAsAttributeManager();

        $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => '1,2,3',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute_ids',
            ]);
    }

    /**
     * attribute_ids must be a JSON list rather than an associative object.
     */
    public function test_attribute_ids_must_be_a_list(): void
    {
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager();

        $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [
                    'first' => $attribute->getKey(),
                ],
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute_ids',
            ]);
    }

    /**
     * Every ID must be a positive integer.
     */
    public function test_attribute_ids_must_be_positive_integers(): void
    {
        $category = Category::factory()->create();

        $this->authenticateAsAttributeManager();

        $url = '/api/categories/'.$category->getKey().'/attributes';

        $this->putJson($url, [
            'attribute_ids' => [
                'not-an-id',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute_ids.0',
            ]);

        $this->putJson($url, [
            'attribute_ids' => [
                0,
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'attribute_ids.0',
            ]);
    }

    /**
     * Unsupported fields cannot be submitted with the assignment payload.
     */
    public function test_unsupported_payload_fields_are_rejected(): void
    {
        $category = Category::factory()->create();
        $attribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager();

        $this->putJson(
            '/api/categories/'.$category->getKey().'/attributes',
            [
                'attribute_ids' => [
                    $attribute->getKey(),
                ],
                'status' => false,
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'status',
            ]);

        $this->assertDatabaseMissing('attribute_category', [
            'category_id' => $category->getKey(),
            'attribute_id' => $attribute->getKey(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Not Found Tests
    |--------------------------------------------------------------------------
    */

    /**
     * A nonexistent category returns 404 on assignment replacement.
     */
    public function test_replace_returns_not_found_for_missing_category(): void
    {
        $attribute = Attribute::factory()->create();

        $this->authenticateAsAttributeManager();

        $this->putJson(
            '/api/categories/999999/attributes',
            [
                'attribute_ids' => [
                    $attribute->getKey(),
                ],
            ],
        )->assertNotFound();

        $this->assertDatabaseMissing('attribute_category', [
            'category_id' => 999999,
            'attribute_id' => $attribute->getKey(),
        ]);
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
}
