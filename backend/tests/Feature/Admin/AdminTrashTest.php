<?php

namespace Tests\Feature\Admin;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminTrashTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $viewPerm = Permission::firstOrCreate(
            ['slug' => 'dashboard.view'],
            ['name' => 'Xem dashboard', 'description' => 'Xem dashboard']
        );

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Quản trị viên', 'description' => 'Admin role', 'is_system' => true]
        );
        $adminRole->permissions()->syncWithoutDetaching([$viewPerm->id]);

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->regularUser = User::factory()->create(['is_active' => true]);
    }

    public function test_guest_cannot_access_trash(): void
    {
        $this->getJson('/api/admin/trash/summary')
            ->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_trash(): void
    {
        $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/admin/trash/summary')
            ->assertForbidden();
    }

    public function test_admin_can_get_trash_summary(): void
    {
        // Create 2 trashed banners and 1 active banner
        $b1 = Banner::create([
            'title' => 'Trashed Banner 1',
            'image_path' => '/img/1.jpg',
            'position' => 'home',
        ]);
        $b1->delete();

        $b2 = Banner::create([
            'title' => 'Trashed Banner 2',
            'image_path' => '/img/2.jpg',
            'position' => 'home',
        ]);
        $b2->delete();

        Banner::create([
            'title' => 'Active Banner',
            'image_path' => '/img/3.jpg',
            'position' => 'home',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/trash/summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'products',
                    'categories',
                    'brands',
                    'banners',
                    'coupons',
                    'total',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['banners']);
        $this->assertGreaterThanOrEqual(2, $data['total']);
    }

    public function test_admin_can_list_trashed_items(): void
    {
        $coupon = Coupon::create([
            'code' => 'TRASHED50',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'is_active' => true,
        ]);
        $coupon->delete();

        $activeCoupon = Coupon::create([
            'code' => 'ACTIVE50',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/trash/coupons')
            ->assertOk()
            ->assertJson([
                'count' => 1,
                'type' => 'coupons',
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('TRASHED50', $data[0]['code']);
    }

    public function test_admin_can_restore_trashed_item(): void
    {
        $brand = Brand::factory()->create(['name' => 'Brand To Restore']);
        $brand->delete();

        $this->assertSoftDeleted('brands', ['id' => $brand->id]);

        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson("/api/admin/trash/brands/{$brand->id}/restore")
            ->assertOk()
            ->assertJsonStructure(['message', 'data']);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_can_restore_all_trashed_items(): void
    {
        $b1 = Banner::create(['title' => 'B1', 'image_path' => '/1.jpg', 'position' => 'home']);
        $b2 = Banner::create(['title' => 'B2', 'image_path' => '/2.jpg', 'position' => 'home']);
        $b1->delete();
        $b2->delete();

        $this->assertEquals(2, Banner::onlyTrashed()->count());

        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/admin/trash/restore-all/banners')
            ->assertOk()
            ->assertJson(['restored_count' => 2]);

        $this->assertEquals(0, Banner::onlyTrashed()->count());
    }

    public function test_admin_can_force_delete_trashed_item(): void
    {
        $coupon = Coupon::create([
            'code' => 'PERMANENTDELETE',
            'discount_type' => 'fixed',
            'discount_value' => 10000,
            'is_active' => true,
        ]);
        $coupon->delete();

        $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/trash/coupons/{$coupon->id}")
            ->assertOk();

        $this->assertDatabaseMissing('coupons', ['id' => $coupon->id]);
    }

    public function test_admin_cannot_force_delete_product_with_orders(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();

        $product = Product::create([
            'name' => 'Product With Order',
            'slug' => 'product-with-order',
            'product_type' => 'phone',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price' => 100000,
            'status' => 'draft',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-' . uniqid(),
            'price' => 100000,
            'original_price' => 100000,
            'is_active' => true,
        ]);

        $product->delete();

        // Create a mock order item referencing this product
        $shipping = ShippingMethod::create([
            'name' => 'Giao hàng',
            'code' => 'STD_' . uniqid(),
            'fee' => 20000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->regularUser->id,
            'order_code' => 'ORD-TRASH-1',
            'payment_method' => 'cod',
            'shipping_method_id' => $shipping->id,
            'shipping_method_code' => $shipping->code,
            'shipping_method_name' => $shipping->name,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'shipping_fee' => 20000,
            'total_amount' => 120000,
            'currency_code' => 'VND',
            'shipping_name' => 'Customer',
            'shipping_phone' => '0901234567',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Cau Giay',
            'shipping_address_line' => '123 Test St',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'variant_name' => 'Default',
            'sku' => $variant->sku,
            'price' => 100000,
            'quantity' => 1,
        ]);

        // Attempt force delete should return 422
        $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/trash/products/{$product->id}")
            ->assertStatus(422);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_admin_can_empty_trash_for_type(): void
    {
        $b1 = Banner::create(['title' => 'Empty 1', 'image_path' => '/1.jpg', 'position' => 'home']);
        $b2 = Banner::create(['title' => 'Empty 2', 'image_path' => '/2.jpg', 'position' => 'home']);
        $b1->delete();
        $b2->delete();

        $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson('/api/admin/trash/empty/banners')
            ->assertOk()
            ->assertJson(['deleted_count' => 2]);

        $this->assertDatabaseMissing('banners', ['id' => $b1->id]);
        $this->assertDatabaseMissing('banners', ['id' => $b2->id]);
    }

    public function test_invalid_trash_type_returns_422(): void
    {
        $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/trash/invalid_type')
            ->assertStatus(422)
            ->assertJson(['message' => 'Invalid trash type: invalid_type']);
    }
}
