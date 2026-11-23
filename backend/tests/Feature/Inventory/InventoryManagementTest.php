<?php

namespace Tests\Feature\Inventory;

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

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $warehouseUser;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create([
            'name' => 'Warehouse',
            'slug' => 'warehouse-test',
            'is_system' => false,
        ]);

        foreach (['inventory.view', 'inventory.import', 'inventory.adjust'] as $slug) {
            $permission = Permission::create([
                'name' => $slug,
                'slug' => $slug,
            ]);
            $role->permissions()->attach($permission->id);
        }

        $this->warehouseUser = User::factory()->create(['is_active' => true]);
        $this->warehouseUser->roles()->attach($role->id);

        $category = Category::create([
            'name' => 'Phones',
            'slug' => 'phones-inventory-test',
            'is_active' => true,
        ]);
        $brand = Brand::create([
            'name' => 'Test Brand',
            'slug' => 'test-brand-inventory',
            'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Inventory Test Phone',
            'slug' => 'inventory-test-phone',
            'status' => 'active',
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'INV-TEST-001',
            'option_signature' => 'color:black',
            'price' => 100000,
            'is_active' => true,
        ]);

        Inventory::create([
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 10,
            'reserved_quantity' => 3,
            'min_stock' => 2,
        ]);
    }

    public function test_import_records_canonical_inventory_transaction(): void
    {
        $response = $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/inventories/import', [
                'product_variant_id' => $this->variant->id,
                'quantity' => 5,
                'idempotency_key' => 'import-test-001',
                'cost_price' => 75000,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 15,
            'reserved_quantity' => 3,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_variant_id' => $this->variant->id,
            'type' => 'import',
            'quantity_delta' => 5,
            'unit_cost' => 75000,
        ]);
    }

    public function test_export_records_negative_export_and_cannot_use_reserved_stock(): void
    {
        $response = $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/inventories/export', [
                'product_variant_id' => $this->variant->id,
                'quantity' => 4,
                'idempotency_key' => 'export-test-001',
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 6,
            'reserved_quantity' => 3,
        ]);
        $this->assertDatabaseHas('inventory_transactions', [
            'product_variant_id' => $this->variant->id,
            'type' => 'export',
            'quantity_delta' => -4,
        ]);

        $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/inventories/export', [
                'product_variant_id' => $this->variant->id,
                'quantity' => 4,
                'idempotency_key' => 'export-test-002',
            ])
            ->assertUnprocessable();

        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 6,
            'reserved_quantity' => 3,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_repeating_import_with_same_key_does_not_add_stock_twice(): void
    {
        $payload = [
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'idempotency_key' => 'import-retry-001',
            'cost_price' => 90000,
            'note' => 'Supplier delivery',
        ];

        $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/inventories/import', $payload)
            ->assertOk();
        $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/inventories/import', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Inventory import was already applied.');

        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 12,
            'reserved_quantity' => 3,
        ]);
        $this->assertSame(90000.0, (float) $this->variant->fresh()->cost_price);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_repeating_export_with_same_key_does_not_remove_stock_twice(): void
    {
        $payload = [
            'product_variant_id' => $this->variant->id,
            'quantity' => 2,
            'idempotency_key' => 'export-retry-001',
            'note' => 'Damaged sample',
        ];

        $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/inventories/export', $payload)
            ->assertOk();
        $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/inventories/export', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'Inventory export was already applied.');

        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 8,
            'reserved_quantity' => 3,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_reusing_inventory_idempotency_key_with_different_payload_conflicts(): void
    {
        $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/inventories/import', [
                'product_variant_id' => $this->variant->id,
                'quantity' => 2,
                'idempotency_key' => 'import-conflict-001',
            ])
            ->assertOk();

        $this->actingAs($this->warehouseUser, 'sanctum')
            ->postJson('/api/inventories/import', [
                'product_variant_id' => $this->variant->id,
                'quantity' => 3,
                'idempotency_key' => 'import-conflict-001',
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('inventories', [
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 12,
            'reserved_quantity' => 3,
        ]);
        $this->assertDatabaseCount('inventory_transactions', 1);
    }

    public function test_customer_cannot_read_or_adjust_inventory(): void
    {
        $customer = User::factory()->create(['is_active' => true]);

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/inventories')
            ->assertForbidden();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/inventories/import', [
                'product_variant_id' => $this->variant->id,
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/products', [])
            ->assertForbidden();
    }
}
