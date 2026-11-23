<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFlashSaleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $view = Permission::firstOrCreate(['slug' => 'flash_sales.view'], ['name' => 'View Flash Sales']);
        $manage = Permission::firstOrCreate(['slug' => 'flash_sales.manage'], ['name' => 'Manage Flash Sales']);
        $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'is_system' => true]);
        $role->permissions()->syncWithoutDetaching([$view->id, $manage->id]);
        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->roles()->attach($role->id);
        $this->customer = User::factory()->create(['is_active' => true]);

        $category = Category::create(['name' => 'Phones', 'slug' => 'admin-sale-phones', 'is_active' => true]);
        $brand = Brand::create(['name' => 'MobieX', 'slug' => 'admin-sale-brand', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Admin sale phone',
            'slug' => 'admin-sale-phone',
            'status' => 'active',
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ADMIN-SALE-1',
            'price' => 1000000,
            'cost_price' => 500000,
            'is_active' => true,
        ]);
    }

    public function test_customer_cannot_access_admin_flash_sale_api(): void
    {
        $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/admin/flash-sales')
            ->assertForbidden();
    }

    public function test_admin_can_create_flash_sale_with_discount_below_current_price(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/flash-sales', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Test Flash Sale')
            ->assertJsonPath('data.items.0.sale_price', '750000.00');

        $this->assertDatabaseHas('flash_sale_items', [
            'flash_sale_id' => $response->json('data.id'),
            'product_variant_id' => $this->variant->id,
            'quantity_limit' => 4,
        ]);
    }

    public function test_admin_cannot_set_flash_sale_price_at_or_above_current_price(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/flash-sales', $this->payload(1000000))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.sale_price');

        $this->assertDatabaseCount('flash_sales', 0);
    }

    public function test_same_variant_cannot_be_scheduled_in_overlapping_campaigns(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/flash-sales', $this->payload())
            ->assertCreated();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/admin/flash-sales', array_merge($this->payload(), [
                'name' => 'Overlapping campaign',
                'slug' => 'overlapping-campaign',
                'start_at' => now()->addMinutes(30)->toISOString(),
                'end_at' => now()->addHours(2)->toISOString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items.0.product_variant_id');

        $this->assertDatabaseCount('flash_sales', 1);
    }

    public function test_variant_options_do_not_expose_cost_price(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/flash-sales/variant-options');

        $response->assertOk()
            ->assertJsonPath('data.0.price', '1000000.00')
            ->assertJsonMissingPath('data.0.cost_price');
    }

    public function test_campaign_with_sold_items_is_cancelled_instead_of_deleted(): void
    {
        $sale = FlashSale::create([
            'name' => 'Used Sale',
            'slug' => 'used-sale',
            'start_at' => now()->subHour(),
            'end_at' => now()->addHour(),
            'status' => 'active',
        ]);
        FlashSaleItem::create([
            'flash_sale_id' => $sale->id,
            'product_variant_id' => $this->variant->id,
            'sale_price' => 750000,
            'quantity_sold' => 1,
        ]);
        $this->actingAs($this->admin, 'sanctum')
            ->deleteJson('/api/admin/flash-sales/'.$sale->id)
            ->assertOk();

        $this->assertDatabaseHas('flash_sales', ['id' => $sale->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('flash_sale_items', ['flash_sale_id' => $sale->id, 'quantity_sold' => 1]);
    }

    private function payload(float $salePrice = 750000): array
    {
        return [
            'name' => 'Test Flash Sale',
            'slug' => 'test-flash-sale',
            'description' => 'Regression test campaign',
            'start_at' => now()->subMinute()->toISOString(),
            'end_at' => now()->addHour()->toISOString(),
            'status' => 'active',
            'items' => [[
                'product_variant_id' => $this->variant->id,
                'sale_price' => $salePrice,
                'quantity_limit' => 4,
            ]],
        ];
    }
}
