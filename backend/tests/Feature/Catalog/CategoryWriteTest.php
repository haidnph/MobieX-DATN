<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryWriteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests cannot create or update categories.
     */
    public function test_guest_cannot_create_or_update_categories(): void
    {
        $category = Category::factory()->create();

        $this->postJson('/api/categories', [
            'name' => 'Phones',
            'slug' => 'phones',
        ])->assertUnauthorized();

        $this->patchJson(
            '/api/categories/'.$category->getKey(),
            [
                'name' => 'Updated Phones',
            ],
        )->assertUnauthorized();
    }

    /**
     * Authenticated users without the required permission receive 403.
     */
    public function test_user_without_categories_permission_cannot_write_categories(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/categories', [
            'name' => 'Phones',
            'slug' => 'phones',
        ])->assertForbidden();

        $this->patchJson(
            '/api/categories/'.$category->getKey(),
            [
                'name' => 'Updated Phones',
            ],
        )->assertForbidden();
    }

    /**
     * Inactive users cannot write categories even when they have permission.
     */
    public function test_inactive_category_manager_receives_forbidden(): void
    {
        $this->authenticateAsCategoryManager([
            'is_active' => false,
        ]);

        $this->postJson('/api/categories', [
            'name' => 'Phones',
            'slug' => 'phones',
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);
    }

    /**
     * A category manager can create a root category.
     */
    public function test_category_manager_can_create_root_category(): void
    {
        $this->authenticateAsCategoryManager();

        $response = $this->postJson('/api/categories', [
            'name' => 'Phones',
            'slug' => 'phones',
        ]);

        $response->assertCreated()
            ->assertJsonPath(
                'message',
                'Category created successfully.',
            )
            ->assertJsonPath('data.category.name', 'Phones')
            ->assertJsonPath('data.category.slug', 'phones')
            ->assertJsonPath('data.category.parent_id', null)
            ->assertJsonPath('data.category.status', true)
            ->assertJsonPath('data.category.sort_order', 0);

        $this->assertDatabaseHas('categories', [
            'name' => 'Phones',
            'slug' => 'phones',
            'parent_id' => null,
            'status' => true,
            'sort_order' => 0,
        ]);

        $this->assertDatabaseCount('categories', 1);
    }

    /**
     * A category manager can create a child category.
     *
     * Nested category names may match root category names.
     */
    public function test_category_manager_can_create_child_category(): void
    {
        $this->authenticateAsCategoryManager();

        $parent = Category::factory()->create([
            'name' => 'Phones',
            'slug' => 'phones',
            'parent_id' => null,
        ]);

        $this->postJson('/api/categories', [
            'name' => 'Phones',
            'slug' => 'phones-child',
            'parent_id' => $parent->getKey(),
        ])
            ->assertCreated()
            ->assertJsonPath('data.category.name', 'Phones')
            ->assertJsonPath(
                'data.category.parent_id',
                $parent->getKey(),
            );

        $this->assertDatabaseHas('categories', [
            'name' => 'Phones',
            'slug' => 'phones-child',
            'parent_id' => $parent->getKey(),
        ]);
    }

    /**
     * Creating a category validates required fields and invalid values.
     */
    public function test_category_creation_validates_fields(): void
    {
        $this->authenticateAsCategoryManager();

        Category::factory()->create([
            'slug' => 'existing-slug',
        ]);

        $this->postJson('/api/categories', [
            'slug' => 'existing-slug',
            'parent_id' => 999999,
            'sort_order' => -1,
            'status' => 'not-a-boolean',
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'slug',
                'parent_id',
                'sort_order',
                'status',
                'is_active',
            ]);
    }

    /**
     * Root category names must be unique.
     */
    public function test_duplicate_root_category_name_is_rejected(): void
    {
        $this->authenticateAsCategoryManager();

        Category::factory()->create([
            'name' => 'Phones',
            'slug' => 'phones',
            'parent_id' => null,
        ]);

        $this->postJson('/api/categories', [
            'name' => 'Phones',
            'slug' => 'phones-another',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->assertDatabaseMissing('categories', [
            'slug' => 'phones-another',
        ]);
    }

    /**
     * A category manager can partially update an existing category.
     *
     * Keeping the current slug must not trigger a duplicate-slug error.
     */
    public function test_category_manager_can_update_category(): void
    {
        $this->authenticateAsCategoryManager();

        $category = Category::factory()->create([
            'name' => 'Old Phones',
            'slug' => 'old-phones',
            'sort_order' => 1,
        ]);

        $this->patchJson(
            '/api/categories/'.$category->getKey(),
            [
                'name' => 'Updated Phones',
                'slug' => 'old-phones',
                'description' => 'Updated category description.',
                'sort_order' => 5,
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Category updated successfully.',
            )
            ->assertJsonPath(
                'data.category.id',
                $category->getKey(),
            )
            ->assertJsonPath(
                'data.category.name',
                'Updated Phones',
            )
            ->assertJsonPath(
                'data.category.slug',
                'old-phones',
            )
            ->assertJsonPath(
                'data.category.description',
                'Updated category description.',
            )
            ->assertJsonPath(
                'data.category.sort_order',
                5,
            );

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
            'name' => 'Updated Phones',
            'slug' => 'old-phones',
            'sort_order' => 5,
        ]);
    }

    /**
     * Updating a root category to another root's name is rejected.
     */
    public function test_update_rejects_duplicate_root_category_name(): void
    {
        $this->authenticateAsCategoryManager();

        $category = Category::factory()->create([
            'name' => 'Phones',
            'slug' => 'phones',
            'parent_id' => null,
        ]);

        Category::factory()->create([
            'name' => 'Tablets',
            'slug' => 'tablets',
            'parent_id' => null,
        ]);

        $this->patchJson(
            '/api/categories/'.$category->getKey(),
            [
                'name' => 'Tablets',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);
    }

    /**
     * Updating a category to another category's slug is rejected.
     */
    public function test_update_rejects_slug_used_by_another_category(): void
    {
        $this->authenticateAsCategoryManager();

        $category = Category::factory()->create([
            'slug' => 'phones',
        ]);

        Category::factory()->create([
            'slug' => 'tablets',
        ]);

        $this->patchJson(
            '/api/categories/'.$category->getKey(),
            [
                'slug' => 'tablets',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);
    }

    /**
     * Empty and unsupported PATCH payloads are rejected.
     */
    public function test_update_rejects_empty_and_unsupported_payloads(): void
    {
        $this->authenticateAsCategoryManager();

        $category = Category::factory()->create();

        $this->patchJson(
            '/api/categories/'.$category->getKey(),
            [],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category',
            ]);

        $this->patchJson(
            '/api/categories/'.$category->getKey(),
            [
                'is_active' => true,
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_active',
                'category',
            ]);
    }

    /**
     * Updating a nonexistent category returns 404.
     */
    public function test_update_returns_not_found_for_missing_category(): void
    {
        $this->authenticateAsCategoryManager();

        $this->patchJson('/api/categories/999999', [
            'name' => 'Updated Phones',
        ])->assertNotFound();
    }

    /**
     * Create an active user with the categories.manage permission.
     *
     * @param  array<string, mixed>  $userAttributes
     */
    private function authenticateAsCategoryManager(
        array $userAttributes = [],
    ): User {
        $user = User::factory()->create($userAttributes);

        $role = Role::factory()->create([
            'name' => 'Category Manager',
            'slug' => 'category-manager',
        ]);

        $permission = Permission::factory()->create([
            'name' => 'Manage Categories',
            'slug' => 'categories.manage',
        ]);

        $role->permissions()->attach(
            $permission->getKey(),
        );

        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
