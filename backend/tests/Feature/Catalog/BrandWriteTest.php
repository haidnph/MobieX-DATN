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
     * Inactive users cannot create brands even if they have the permission.
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
     *
     * Omitting status must preserve the database default (true).
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

    /**
     * Create an active user with the brands.manage permission and authenticate.
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
