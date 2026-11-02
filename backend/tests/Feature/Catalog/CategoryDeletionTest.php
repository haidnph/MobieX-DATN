<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryDeletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guest cannot delete a category.
     */
    public function test_guest_cannot_delete_category(): void
    {
        $category = Category::factory()->create();

        $this->deleteJson(
            '/api/categories/'.$category->getKey(),
        )->assertUnauthorized();

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
        ]);
    }

    /**
     * User without categories.manage cannot delete a category.
     */
    public function test_user_without_permission_cannot_delete_category(): void
    {
        $user = User::factory()->create();

        $category = Category::factory()->create();

        Sanctum::actingAs($user);

        $this->deleteJson(
            '/api/categories/'.$category->getKey(),
        )->assertForbidden();

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
        ]);
    }

    /**
     * Inactive category managers cannot delete categories.
     */
    public function test_inactive_category_manager_cannot_delete_category(): void
    {
        $this->authenticateAsCategoryManager([
            'is_active' => false,
        ]);

        $category = Category::factory()->create();

        $this->deleteJson(
            '/api/categories/'.$category->getKey(),
        )
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
        ]);
    }

    /**
     * A category without children or available product references
     * can be hard-deleted.
     *
     * This test also covers the current development schema where
     * the products table has not yet been integrated.
     */
    public function test_category_manager_can_delete_unreferenced_category(): void
    {
        $this->authenticateAsCategoryManager();

        $category = Category::factory()->create([
            'name' => 'Temporary Category',
            'slug' => 'temporary-category',
            'status' => true,
        ]);

        $this->deleteJson(
            '/api/categories/'.$category->getKey(),
        )
            ->assertOk()
            ->assertJson([
                'message' => 'Category deleted successfully.',
            ]);

        $this->assertDatabaseMissing('categories', [
            'id' => $category->getKey(),
        ]);
    }

    /**
     * A parent cannot be deleted while it has child categories.
     *
     * Hidden children must also prevent parent deletion.
     */
    public function test_category_cannot_be_deleted_while_it_has_children(): void
    {
        $this->authenticateAsCategoryManager();

        $parent = Category::factory()->create([
            'name' => 'Phones',
            'slug' => 'phones',
            'parent_id' => null,
        ]);

        $child = Category::factory()
            ->for($parent, 'parent')
            ->create([
                'name' => 'Smartphones',
                'slug' => 'smartphones',
                'status' => false,
            ]);

        $this->deleteJson(
            '/api/categories/'.$parent->getKey(),
        )
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Category cannot be deleted while it has child categories. Set status to false to hide it instead.',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $parent->getKey(),
            'parent_id' => null,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $child->getKey(),
            'parent_id' => $parent->getKey(),
            'status' => false,
        ]);
    }

    /**
     * Deleting a nonexistent category returns 404.
     */
    public function test_delete_returns_not_found_for_missing_category(): void
    {
        $this->authenticateAsCategoryManager();

        $this->deleteJson('/api/categories/999999')
            ->assertNotFound();
    }

    /**
     * Create an active user with categories.manage permission.
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
