<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BrandWriteTest extends TestCase
{
    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Brand Creation Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests cannot create brands.
     */
    public function test_guest_cannot_create_brand(): void
    {
        $this->postJson('/api/brands', [
            'name' => 'Apple',
            'slug' => 'apple',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('brands', 0);
    }

    /**
     * Authenticated users without brands.manage cannot create brands.
     */
    public function test_user_without_brand_permission_cannot_create_brand(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/brands', [
            'name' => 'Apple',
            'slug' => 'apple',
        ])->assertForbidden();

        $this->assertDatabaseCount('brands', 0);
    }

    /**
     * Inactive users cannot create brands even when they have permission.
     */
    public function test_inactive_brand_manager_cannot_create_brand(): void
    {
        $this->authenticateAsBrandManager([
            'is_active' => false,
        ]);

        $this->postJson('/api/brands', [
            'name' => 'Apple',
            'slug' => 'apple',
        ])
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseCount('brands', 0);
    }

    /**
     * A brand manager can create a brand with optional metadata.
     */
    public function test_brand_manager_can_create_brand(): void
    {
        $this->authenticateAsBrandManager();

        $response = $this->postJson('/api/brands', [
            'name' => 'Samsung',
            'slug' => 'samsung',
            'logo' => 'brands/samsung.png',
            'description' => 'Samsung smartphone brand.',
        ]);

        $response->assertCreated()
            ->assertJsonPath(
                'message',
                'Brand created successfully.',
            )
            ->assertJsonPath('data.brand.name', 'Samsung')
            ->assertJsonPath('data.brand.slug', 'samsung')
            ->assertJsonPath('data.brand.logo', 'brands/samsung.png')
            ->assertJsonPath(
                'data.brand.description',
                'Samsung smartphone brand.',
            )
            ->assertJsonPath('data.brand.status', true)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'brand' => [
                        'id',
                        'name',
                        'slug',
                        'logo',
                        'description',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('brands', [
            'name' => 'Samsung',
            'slug' => 'samsung',
            'logo' => 'brands/samsung.png',
            'description' => 'Samsung smartphone brand.',
            'status' => true,
        ]);

        $this->assertDatabaseCount('brands', 1);
    }

    /**
     * A brand manager may explicitly create a hidden brand.
     */
    public function test_brand_manager_can_create_brand_with_status_false(): void
    {
        $this->authenticateAsBrandManager();

        $this->postJson('/api/brands', [
            'name' => 'Hidden Brand',
            'slug' => 'hidden-brand',
            'status' => false,
        ])
            ->assertCreated()
            ->assertJsonPath('data.brand.status', false);

        $this->assertDatabaseHas('brands', [
            'name' => 'Hidden Brand',
            'slug' => 'hidden-brand',
            'status' => false,
        ]);
    }

    /**
     * Duplicate names and slugs are rejected by request validation.
     */
    public function test_brand_creation_rejects_duplicate_name_and_slug(): void
    {
        $this->authenticateAsBrandManager();

        Brand::factory()->create([
            'name' => 'Apple',
            'slug' => 'apple',
        ]);

        $this->postJson('/api/brands', [
            'name' => 'Apple',
            'slug' => 'another-slug',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->postJson('/api/brands', [
            'name' => 'Samsung',
            'slug' => 'apple',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);

        $this->assertDatabaseCount('brands', 1);
    }

    /**
     * Required fields, field constraints and unsupported keys are validated.
     */
    public function test_brand_creation_validates_payload(): void
    {
        $this->authenticateAsBrandManager();

        $this->postJson('/api/brands', [
            'logo' => str_repeat('a', 256),
            'status' => 'not-a-boolean',
            'is_active' => true,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'slug',
                'logo',
                'status',
                'is_active',
            ]);

        $this->assertDatabaseCount('brands', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Brand Update Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Guests cannot update brands.
     */
    public function test_guest_cannot_update_brand(): void
    {
        $brand = Brand::factory()->create();

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'name' => 'Updated Brand',
            ],
        )->assertUnauthorized();

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'name' => $brand->name,
        ]);
    }

    /**
     * Authenticated users without brands.manage cannot update brands.
     */
    public function test_user_without_brand_permission_cannot_update_brand(): void
    {
        $brand = Brand::factory()->create();

        Sanctum::actingAs(User::factory()->create());

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'name' => 'Updated Brand',
            ],
        )->assertForbidden();

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'name' => $brand->name,
        ]);
    }

    /**
     * Inactive users cannot update brands even when they have permission.
     */
    public function test_inactive_brand_manager_cannot_update_brand(): void
    {
        $brand = Brand::factory()->create();

        $this->authenticateAsBrandManager([
            'is_active' => false,
        ]);

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'name' => 'Updated Brand',
            ],
        )
            ->assertForbidden()
            ->assertJson([
                'message' => 'This account is inactive.',
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'name' => $brand->name,
        ]);
    }

    /**
     * A brand manager can update a brand successfully.
     */
    public function test_brand_manager_can_update_brand(): void
    {
        $this->authenticateAsBrandManager();

        $brand = Brand::factory()->create([
            'name' => 'Old Samsung',
            'slug' => 'old-samsung',
            'logo' => 'brands/old-samsung.png',
            'description' => 'Old description.',
            'status' => true,
        ]);

        $response = $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'name' => 'Samsung',
                'slug' => 'samsung',
                'logo' => 'brands/samsung.png',
                'description' => 'Updated Samsung description.',
                'status' => true,
            ],
        );

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Brand updated successfully.',
            )
            ->assertJsonPath('data.brand.id', $brand->getKey())
            ->assertJsonPath('data.brand.name', 'Samsung')
            ->assertJsonPath('data.brand.slug', 'samsung')
            ->assertJsonPath('data.brand.logo', 'brands/samsung.png')
            ->assertJsonPath(
                'data.brand.description',
                'Updated Samsung description.',
            )
            ->assertJsonPath('data.brand.status', true);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'name' => 'Samsung',
            'slug' => 'samsung',
            'logo' => 'brands/samsung.png',
            'description' => 'Updated Samsung description.',
            'status' => true,
        ]);
    }

    /**
     * PATCH updates only supplied fields and preserves omitted attributes.
     */
    public function test_partial_update_preserves_omitted_fields(): void
    {
        $this->authenticateAsBrandManager();

        $brand = Brand::factory()->create([
            'name' => 'Samsung',
            'slug' => 'samsung',
            'logo' => 'brands/samsung.png',
            'description' => 'Original description.',
            'status' => true,
        ]);

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'description' => 'Updated description.',
            ],
        )
            ->assertOk()
            ->assertJsonPath(
                'data.brand.description',
                'Updated description.',
            )
            ->assertJsonPath('data.brand.name', 'Samsung')
            ->assertJsonPath('data.brand.slug', 'samsung')
            ->assertJsonPath('data.brand.logo', 'brands/samsung.png')
            ->assertJsonPath('data.brand.status', true);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'name' => 'Samsung',
            'slug' => 'samsung',
            'logo' => 'brands/samsung.png',
            'description' => 'Updated description.',
            'status' => true,
        ]);
    }

    /**
     * Keeping the current name and slug must not trigger unique validation.
     */
    public function test_update_accepts_unchanged_name_and_slug(): void
    {
        $this->authenticateAsBrandManager();

        $brand = Brand::factory()->create([
            'name' => 'Samsung',
            'slug' => 'samsung',
        ]);

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'name' => 'Samsung',
                'slug' => 'samsung',
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.brand.name', 'Samsung')
            ->assertJsonPath('data.brand.slug', 'samsung');

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'name' => 'Samsung',
            'slug' => 'samsung',
        ]);
    }

    /**
     * Duplicate names and slugs belonging to another brand are rejected.
     */
    public function test_update_rejects_duplicate_name_and_slug(): void
    {
        $this->authenticateAsBrandManager();

        $brand = Brand::factory()->create([
            'name' => 'Samsung',
            'slug' => 'samsung',
        ]);

        Brand::factory()->create([
            'name' => 'Apple',
            'slug' => 'apple',
        ]);

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'name' => 'Apple',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
            ]);

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'slug' => 'apple',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'slug',
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'name' => 'Samsung',
            'slug' => 'samsung',
        ]);
    }

    /**
     * Empty and unsupported PATCH payloads are rejected.
     */
    public function test_update_rejects_empty_and_unsupported_payloads(): void
    {
        $this->authenticateAsBrandManager();

        $brand = Brand::factory()->create();

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'brand',
            ]);

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'is_active' => true,
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'is_active',
                'brand',
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'status' => true,
        ]);
    }

    /**
     * PATCH validates field types and length constraints.
     */
    public function test_update_validates_fields(): void
    {
        $this->authenticateAsBrandManager();

        $brand = Brand::factory()->create();

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'name' => '',
                'slug' => '',
                'logo' => str_repeat('a', 256),
                'status' => 'not-a-boolean',
            ],
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'slug',
                'logo',
                'status',
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'name' => $brand->name,
            'slug' => $brand->slug,
        ]);
    }

    /**
     * A brand manager can remove nullable logo and description values.
     */
    public function test_update_can_clear_nullable_fields(): void
    {
        $this->authenticateAsBrandManager();

        $brand = Brand::factory()->create([
            'logo' => 'brands/samsung.png',
            'description' => 'Samsung smartphone brand.',
        ]);

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'logo' => null,
                'description' => null,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.brand.logo', null)
            ->assertJsonPath('data.brand.description', null);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'logo' => null,
            'description' => null,
        ]);
    }

    /**
     * A brand manager can hide a brand through status=false.
     */
    public function test_brand_manager_can_hide_brand(): void
    {
        $this->authenticateAsBrandManager();

        $brand = Brand::factory()->create([
            'status' => true,
        ]);

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'status' => false,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.brand.status', false);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'status' => false,
        ]);

        // Hidden brands must not appear in public read endpoints.
        $this->getJson(
            '/api/brands/'.$brand->getKey(),
        )->assertNotFound();
    }

    /**
     * Administrators can reactivate a hidden brand.
     */
    public function test_brand_manager_can_reactivate_hidden_brand(): void
    {
        $this->authenticateAsBrandManager();

        $brand = Brand::factory()->create([
            'status' => false,
        ]);

        $this->getJson(
            '/api/brands/'.$brand->getKey(),
        )->assertNotFound();

        $this->patchJson(
            '/api/brands/'.$brand->getKey(),
            [
                'status' => true,
            ],
        )
            ->assertOk()
            ->assertJsonPath('data.brand.status', true);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->getKey(),
            'status' => true,
        ]);

        $this->getJson(
            '/api/brands/'.$brand->getKey(),
        )->assertOk();
    }

    /**
     * Updating a nonexistent brand returns 404.
     */
    public function test_update_returns_not_found_for_missing_brand(): void
    {
        $this->authenticateAsBrandManager();

        $this->patchJson('/api/brands/999999', [
            'name' => 'Updated Brand',
        ])->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Test Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Create an active user with the brands.manage permission.
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
