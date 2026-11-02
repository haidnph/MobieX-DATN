<?php

namespace Tests\Feature\Catalog;

use App\Models\Category;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A category cannot be its own parent.
     */
    public function test_category_cannot_be_its_own_parent(): void
    {
        $this->authenticateAsCategoryManager();

        $category = Category::factory()->create([
            'parent_id' => null,
        ]);

        $this->patchJson(
            '/api/categories/'.$category->getKey(),
            [
                'parent_id' => $category->getKey(),
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'parent_id',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
            'parent_id' => null,
        ]);
    }

    /**
     * A parent cannot become a child of its own direct child.
     *
     * Initial:
     * Root -> Child
     *
     * Invalid:
     * Root -> Child -> Root
     */
    public function test_category_cannot_become_child_of_its_direct_child(): void
    {
        $this->authenticateAsCategoryManager();

        $root = Category::factory()->create([
            'parent_id' => null,
        ]);

        $child = Category::factory()
            ->for($root, 'parent')
            ->create();

        $this->patchJson(
            '/api/categories/'.$root->getKey(),
            [
                'parent_id' => $child->getKey(),
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'parent_id',
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $root->getKey(),
            'parent_id' => null,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $child->getKey(),
            'parent_id' => $root->getKey(),
        ]);
    }

    /**
     * A category cannot become a child of its grandchild.
     *
     * Initial:
     * Root -> Child -> Grandchild
     *
     * Invalid:
     * Root -> Child -> Grandchild -> Root
     */
    public function test_category_cannot_become_child_of_descendant_three_levels_away(): void
    {
        $this->authenticateAsCategoryManager();

        $root = Category::factory()->create([
            'name' => 'Phones',
            'slug' => 'phones',
            'parent_id' => null,
        ]);

        $child = Category::factory()
            ->for($root, 'parent')
            ->create([
                'name' => 'Smartphones',
                'slug' => 'smartphones',
            ]);

        $grandchild = Category::factory()
            ->for($child, 'parent')
            ->create([
                'name' => 'Android',
                'slug' => 'android',
            ]);

        $this->patchJson(
            '/api/categories/'.$root->getKey(),
            [
                'parent_id' => $grandchild->getKey(),
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'parent_id',
            ]);

        // The failed update must not modify the hierarchy.
        $this->assertDatabaseHas('categories', [
            'id' => $root->getKey(),
            'parent_id' => null,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $child->getKey(),
            'parent_id' => $root->getKey(),
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $grandchild->getKey(),
            'parent_id' => $child->getKey(),
        ]);
    }

    /**
     * A category can be moved under an unrelated category.
     *
     * A valid move must not be rejected by cycle protection.
     */
    public function test_category_can_be_moved_under_an_unrelated_parent(): void
    {
        $this->authenticateAsCategoryManager();

        $oldParent = Category::factory()->create([
            'name' => 'Old Parent',
            'slug' => 'old-parent',
            'parent_id' => null,
        ]);

        $newParent = Category::factory()->create([
            'name' => 'New Parent',
            'slug' => 'new-parent',
            'parent_id' => null,
        ]);

        $category = Category::factory()
            ->for($oldParent, 'parent')
            ->create([
                'name' => 'Phones',
                'slug' => 'phones',
            ]);

        $child = Category::factory()
            ->for($category, 'parent')
            ->create([
                'name' => 'Android',
                'slug' => 'android',
            ]);

        $this->patchJson(
            '/api/categories/'.$category->getKey(),
            [
                'parent_id' => $newParent->getKey(),
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'data.category.id',
                $category->getKey(),
            )
            ->assertJsonPath(
                'data.category.parent_id',
                $newParent->getKey(),
            );

        // The category was moved successfully.
        $this->assertDatabaseHas('categories', [
            'id' => $category->getKey(),
            'parent_id' => $newParent->getKey(),
        ]);

        // Existing descendants must remain attached.
        $this->assertDatabaseHas('categories', [
            'id' => $child->getKey(),
            'parent_id' => $category->getKey(),
        ]);
    }

    /**
     * Create an active user with categories.manage permission.
     */
    private function authenticateAsCategoryManager(): User
    {
        $user = User::factory()->create();

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
