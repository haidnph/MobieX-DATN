<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\ProductSpecificationDefinition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSpecificationSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $regularUser;
    private Product $product;
    private ProductSpecificationDefinition $defCpu;
    private ProductSpecificationDefinition $defRam;
    private ProductSpecificationDefinition $defNfc;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Quản trị viên', 'description' => 'Admin role', 'is_system' => true]
        );

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->regularUser = User::factory()->create(['is_active' => true]);

        $category = Category::create([
            'name' => 'Điện thoại',
            'slug' => 'dien-thoai',
            'status' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'status' => true,
        ]);

        $this->product = Product::create([
            'name' => 'iPhone 16 Pro Max',
            'slug' => 'iphone-16-pro-max',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'status' => 'active',
            'base_price' => 34990000,
        ]);

        $this->defCpu = ProductSpecificationDefinition::create([
            'code' => 'cpu',
            'name' => 'Vi xử lý',
            'value_type' => 'text',
            'applies_to' => 'phone',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->defRam = ProductSpecificationDefinition::create([
            'code' => 'ram',
            'name' => 'RAM',
            'unit' => 'GB',
            'value_type' => 'number',
            'applies_to' => 'phone',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->defNfc = ProductSpecificationDefinition::create([
            'code' => 'nfc',
            'name' => 'NFC',
            'value_type' => 'boolean',
            'applies_to' => 'phone',
            'sort_order' => 3,
            'is_active' => true,
        ]);
    }

    public function test_guest_cannot_sync_specifications(): void
    {
        $response = $this->postJson("/api/admin/products/{$this->product->id}/specifications", [
            'specs' => [
                ['definition_id' => $this->defCpu->id, 'value' => 'Apple A18 Pro'],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_admin_can_sync_specifications(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/admin/products/{$this->product->id}/specifications", [
                'specs' => [
                    ['definition_id' => $this->defCpu->id, 'value' => 'Apple A18 Pro'],
                    ['definition_id' => $this->defRam->id, 'value' => 8],
                    ['definition_id' => $this->defNfc->id, 'value' => true],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Specifications synced',
                'count' => 3,
            ]);

        $this->assertDatabaseHas('product_specifications', [
            'product_id' => $this->product->id,
            'specification_definition_id' => $this->defCpu->id,
            'value_text' => 'Apple A18 Pro',
        ]);

        $this->assertDatabaseHas('product_specifications', [
            'product_id' => $this->product->id,
            'specification_definition_id' => $this->defRam->id,
            'numeric_value' => 8.0000,
        ]);

        $this->assertDatabaseHas('product_specifications', [
            'product_id' => $this->product->id,
            'specification_definition_id' => $this->defNfc->id,
            'value_boolean' => true,
        ]);
    }

    public function test_sync_updates_existing_specifications(): void
    {
        // First sync
        ProductSpecification::create([
            'product_id' => $this->product->id,
            'specification_definition_id' => $this->defCpu->id,
            'value_text' => 'Apple A17 Pro',
        ]);

        // Second sync updates it
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/admin/products/{$this->product->id}/specifications", [
                'specs' => [
                    ['definition_id' => $this->defCpu->id, 'value' => 'Apple A18 Pro'],
                ],
            ])
            ->assertStatus(200);

        $this->assertDatabaseCount('product_specifications', 1);
        $this->assertDatabaseHas('product_specifications', [
            'product_id' => $this->product->id,
            'specification_definition_id' => $this->defCpu->id,
            'value_text' => 'Apple A18 Pro',
        ]);
    }

    public function test_admin_can_manage_spec_definitions(): void
    {
        // List definitions
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/spec-definitions')
            ->assertStatus(200)
            ->assertJsonCount(3);

        // Create new definition
        $createRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/admin/spec-definitions', [
                'name' => 'Pin',
                'unit' => 'mAh',
                'value_type' => 'number',
                'applies_to' => 'phone',
                'sort_order' => 4,
            ])
            ->assertStatus(201)
            ->assertJson([
                'name' => 'Pin',
                'unit' => 'mAh',
                'value_type' => 'number',
            ]);

        $newId = $createRes->json('id');

        // Update definition
        $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/admin/spec-definitions/{$newId}", [
                'name' => 'Dung lượng pin',
            ])
            ->assertStatus(200)
            ->assertJson([
                'name' => 'Dung lượng pin',
            ]);

        // Delete definition
        $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/spec-definitions/{$newId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('product_specification_definitions', [
            'id' => $newId,
        ]);
    }
}
