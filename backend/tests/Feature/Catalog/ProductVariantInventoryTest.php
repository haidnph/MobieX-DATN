<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(
            ['slug' => 'products.manage'],
            ['name' => 'Manage products']
        );
        $role = Role::create([
            'name' => 'Variant manager',
            'slug' => 'variant-manager-test',
            'is_system' => false,
        ]);
        $role->permissions()->attach($permission->id);

        $this->manager = User::factory()->create(['is_active' => true]);
        $this->manager->roles()->attach($role->id);

        $category = Category::create([
            'name' => 'Variant test category',
            'slug' => 'variant-test-category',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'Variant test brand',
            'slug' => 'variant-test-brand',
            'is_active' => true,
        ]);
        $this->product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Variant test phone',
            'slug' => 'variant-test-phone',
            'status' => 'active',
        ]);
    }

    public function test_creating_variant_writes_canonical_inventory_and_import_history(): void
    {
        $response = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/product-variants', [
                'product_id' => $this->product->id,
                'price' => 100000,
                'quantity' => 8,
                'min_stock' => 2,
            ]);

        $response->assertCreated();
        $variantId = $response->json('data.id');

        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $variantId,
            'on_hand_quantity' => 8,
            'reserved_quantity' => 0,
            'min_stock' => 2,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_variant_id' => $variantId,
            'type' => 'import',
            'quantity_delta' => 8,
            'reference_type' => 'variant_create',
        ]);
    }

    public function test_variant_read_endpoints_require_product_management_permission_and_keep_cost_visible_to_managers(): void
    {
        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'PRIVATE-COST-1',
            'price' => 100000,
            'cost_price' => 70000,
            'is_active' => true,
        ]);

        $unauthorizedUser = User::factory()->create(['is_active' => true]);

        $this->actingAs($unauthorizedUser, 'sanctum')
            ->getJson('/api/product-variants')
            ->assertForbidden();

        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/product-variants/'.$variant->id)
            ->assertOk()
            ->assertJsonPath('data.cost_price', '70000.00');

        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/product-variants')
            ->assertOk()
            ->assertJsonPath('data.data.0.cost_price', '70000.00');
    }

    public function test_admin_product_details_keep_cost_price_available_to_product_managers(): void
    {
        ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'ADMIN-COST-1',
            'price' => 100000,
            'cost_price' => 70000,
            'is_active' => true,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/admin/products/'.$this->product->id)
            ->assertOk()
            ->assertJsonPath('data.variants.0.cost_price', '70000.00');
    }

    public function test_inventory_viewers_can_read_cost_price_through_the_inventory_api(): void
    {
        $inventoryPermission = Permission::firstOrCreate(
            ['slug' => 'inventory.view'],
            ['name' => 'View inventory']
        );
        $managerRole = $this->manager->roles()->firstOrFail();
        $managerRole->permissions()->syncWithoutDetaching([$inventoryPermission->id]);

        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'INVENTORY-COST-1',
            'price' => 100000,
            'cost_price' => 70000,
            'is_active' => true,
        ]);
        Inventory::create([
            'product_variant_id' => $variant->id,
            'on_hand_quantity' => 2,
            'reserved_quantity' => 0,
            'min_stock' => 1,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->getJson('/api/inventories')
            ->assertOk()
            ->assertJsonPath('data.0.variant.cost_price', '70000.00');
    }

    public function test_updating_variant_stock_writes_signed_export_and_respects_reservations(): void
    {
        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'VARIANT-STOCK-TEST',
            'option_signature' => 'color:black',
            'price' => 100000,
            'is_active' => true,
        ]);
        Inventory::create([
            'product_variant_id' => $variant->id,
            'on_hand_quantity' => 8,
            'reserved_quantity' => 3,
            'min_stock' => 2,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/product-variants/{$variant->id}", ['quantity' => 6])
            ->assertOk();

        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $variant->id,
            'on_hand_quantity' => 6,
            'reserved_quantity' => 3,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_variant_id' => $variant->id,
            'type' => 'export',
            'quantity_delta' => -2,
        ]);

        $this->actingAs($this->manager, 'sanctum')
            ->patchJson("/api/product-variants/{$variant->id}", ['quantity' => 2])
            ->assertUnprocessable();

        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $variant->id,
            'on_hand_quantity' => 6,
            'reserved_quantity' => 3,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_variant_with_inventory_history_cannot_be_deleted(): void
    {
        $variantId = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/product-variants', [
                'product_id' => $this->product->id,
                'price' => 100000,
                'quantity' => 2,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->manager, 'sanctum')
            ->deleteJson("/api/product-variants/{$variantId}")
            ->assertStatus(409);

        $this->assertDatabaseHas('inventory_transactions', [
            'product_variant_id' => $variantId,
            'type' => 'import',
            'quantity_delta' => 2,
        ]);
    }

    public function test_unused_variant_can_be_removed_without_dangling_inventory(): void
    {
        $variantId = $this->actingAs($this->manager, 'sanctum')
            ->postJson('/api/product-variants', [
                'product_id' => $this->product->id,
                'price' => 100000,
                'quantity' => 0,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->actingAs($this->manager, 'sanctum')
            ->deleteJson("/api/product-variants/{$variantId}")
            ->assertOk();

        $this->assertSoftDeleted('product_variants', ['id' => $variantId]);
        $this->assertDatabaseMissing('inventories', ['product_variant_id' => $variantId]);
    }
}
