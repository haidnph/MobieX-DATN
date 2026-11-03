<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandDeletionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests cannot delete brands.
     */
    public function test_guest_cannot_delete_brand(): void
    {
        $brand = Brand::factory()->create();

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )->assertUnauthorized();

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
        ]);
    }

    /**
     * Users without brands.manage cannot delete brands.
     */
    public function test_user_without_permission_cannot_delete_brand(): void
    {
        $brand = Brand::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )->assertForbidden();

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
        ]);
    }

    /**
     * Inactive brand managers cannot delete brands.
     */
    public function test_inactive_brand_manager_cannot_delete_brand(): void
    {
        $this->authenticateAsBrandManager([
            'is_active' => false,
        ]);

        $brand = Brand::factory()->create();

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
        ]);
    }

    /**
     * A brand can be deleted when the Product module
     * has not integrated its products table yet.
     *
     * No placeholder products table must be created.
     */
    public function test_brand_manager_can_delete_brand_when_products_table_is_absent(): void
    {
        $this->authenticateAsBrandManager();

        $this->assertFalse(
            Schema::hasTable('products'),
        );

        $brand = Brand::factory()->create([
            'name' => 'Temporary Brand',
            'slug' => 'temporary-brand',
        ]);

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )
            ->assertOk()
            ->assertJson([
                'message' => 'Brand deleted successfully.',
            ]);

        $this->assertDatabaseMissing('brands', [
            'id' => $brand->getKey(),
        ]);

        $this->assertFalse(
            Schema::hasTable('products'),
        );
    }

    /**
     * An unreferenced brand can be deleted when the Product schema
     * has a valid restrictive foreign key.
     */
    public function test_brand_manager_can_delete_unreferenced_brand_with_restrictive_foreign_key(): void
    {
        $this->authenticateAsBrandManager();

        $this->createProductsTable(
            true,
            true,
            'restrict',
        );

        $brand = Brand::factory()->create();

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )
            ->assertOk()
            ->assertJson([
                'message' => 'Brand deleted successfully.',
            ]);

        $this->assertDatabaseMissing('brands', [
            'id' => $brand->getKey(),
        ]);
    }

    /**
     * A referenced brand cannot be deleted.
     *
     * The API must return 409 and preserve both the brand and product.
     */
    public function test_brand_cannot_be_deleted_while_products_reference_it(): void
    {
        $this->authenticateAsBrandManager();

        $this->createProductsTable(
            true,
            true,
            'restrict',
        );

        $brand = Brand::factory()->create([
            'name' => 'Samsung',
            'slug' => 'samsung',
        ]);

        DB::table('products')->insert([
            'brand_id' => $brand->getKey(),
        ]);

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Brand cannot be deleted while products reference it. Set status to false to hide the brand instead.',
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'name' => 'Samsung',
        ]);

        $this->assertDatabaseHas('products', [
            'brand_id' => $brand->getKey(),
        ]);
    }

    /**
     * When products exists without brand_id, deletion must fail safely.
     *
     * The application must not assume the relationship is absent
     * merely because the expected column has not been integrated.
     */
    public function test_brand_cannot_be_deleted_when_products_brand_id_is_missing(): void
    {
        $this->authenticateAsBrandManager();

        $this->createProductsTable(
            false,
            false,
        );

        $brand = Brand::factory()->create();

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Brand cannot be deleted safely because the products.brand_id reference is unavailable. Set status to false to hide the brand instead.',
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
        ]);
    }

    /**
     * A products table without an actual foreign key cannot provide
     * sufficient protection against concurrent product creation.
     *
     * Deletion must fail closed even if no product currently references
     * the brand.
     */
    public function test_brand_cannot_be_deleted_without_product_brand_foreign_key(): void
    {
        $this->authenticateAsBrandManager();

        $this->createProductsTable(
            true,
            false,
        );

        $brand = Brand::factory()->create();

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Brand cannot be deleted safely because products.brand_id must reference brands.id with ON DELETE RESTRICT or NO ACTION. Configure the foreign key in the Product schema, or set status to false to hide the brand instead.',
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
        ]);
    }

    /**
     * Cascading foreign keys are not accepted because deleting a brand
     * must never implicitly delete its products.
     */
    public function test_brand_cannot_be_deleted_with_cascading_product_foreign_key(): void
    {
        $this->authenticateAsBrandManager();

        $this->createProductsTable(
            true,
            true,
            'cascade',
        );

        $brand = Brand::factory()->create();

        DB::table('products')->insert([
            'brand_id' => $brand->getKey(),
        ]);

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Brand cannot be deleted safely because products.brand_id must reference brands.id with ON DELETE RESTRICT or NO ACTION. Configure the foreign key in the Product schema, or set status to false to hide the brand instead.',
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
        ]);

        $this->assertDatabaseHas('products', [
            'brand_id' => $brand->getKey(),
        ]);
    }

    /**
     * The database must remain the final authority.
     *
     * If another table references a brand, its foreign key constraint
     * must prevent deletion and the API must return 409 instead of
     * exposing a raw SQL exception.
     */
    public function test_database_foreign_key_violation_returns_conflict(): void
    {
        $this->authenticateAsBrandManager();

        Schema::create('brand_references', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('brand_id')
                ->constrained('brands')
                ->restrictOnDelete();
        });

        $brand = Brand::factory()->create();

        DB::table('brand_references')->insert([
            'brand_id' => $brand->getKey(),
        ]);

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )
            ->assertConflict()
            ->assertJson([
                'message' => 'Brand cannot be deleted because another record references it. Set status to false to hide the brand instead.',
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
        ]);

        $this->assertDatabaseHas('brand_references', [
            'brand_id' => $brand->getKey(),
        ]);
    }

    /**
     * A hidden brand without references can still be deleted.
     *
     * Visibility does not determine whether a brand is deletable.
     * Referential integrity is the deciding factor.
     */
    public function test_brand_manager_can_delete_hidden_brand_without_references(): void
    {
        $this->authenticateAsBrandManager();

        $brand = Brand::factory()->create([
            'status' => false,
        ]);

        $this->deleteJson(
            '/api/brands/'.$brand->getKey(),
        )
            ->assertOk()
            ->assertJson([
                'message' => 'Brand deleted successfully.',
            ]);

        $this->assertDatabaseMissing('brands', [
            'id' => $brand->getKey(),
        ]);
    }

    /**
     * Deleting a nonexistent brand returns 404.
     */
    public function test_delete_returns_not_found_for_missing_brand(): void
    {
        $this->authenticateAsBrandManager();

        $this->deleteJson('/api/brands/999999')
            ->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Test Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create a test products table with a configurable brand reference.
     *
     * @param  string  $onDelete  restrict|cascade|set null|no action
     */
    private function createProductsTable(
        bool $includeBrandId = true,
        bool $addBrandForeignKey = true,
        string $onDelete = 'restrict',
    ): void {
        Schema::create('products', function (Blueprint $table) use (
            $includeBrandId,
            $addBrandForeignKey,
            $onDelete,
        ): void {
            $table->id();

            if (! $includeBrandId) {
                return;
            }

            $table->unsignedBigInteger('brand_id')->nullable();

            if (! $addBrandForeignKey) {
                return;
            }

            $foreignKey = $table
                ->foreign('brand_id')
                ->references('id')
                ->on('brands');

            if ($onDelete === 'restrict') {
                $foreignKey->restrictOnDelete();
            } elseif ($onDelete === 'cascade') {
                $foreignKey->cascadeOnDelete();
            } elseif ($onDelete === 'set null') {
                $foreignKey->nullOnDelete();
            }

            // Without an explicit action, the database uses NO ACTION.
        });
    }

    /**
     * Create an active user with brands.manage permission.
     *
     * @param  array<string, mixed>  $userAttributes
     */
    private function authenticateAsBrandManager(
        array $userAttributes = [],
    ): User {
        $user = User::factory()->create($userAttributes);

        $role = Role::factory()->create([
            'name' => 'Brand Manager',
            'slug' => 'brand-manager',
        ]);

        $permission = Permission::factory()->create([
            'name' => 'Manage Brands',
            'slug' => 'brands.manage',
        ]);

        $role->permissions()->attach(
            $permission->getKey(),
        );

        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
