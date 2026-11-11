<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOmnisearchTest extends TestCase
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

    public function test_guest_cannot_access_omnisearch(): void
    {
        $this->getJson('/api/admin/omnisearch')
            ->assertUnauthorized();
    }

    public function test_regular_user_cannot_access_omnisearch(): void
    {
        $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/admin/omnisearch')
            ->assertForbidden();
    }

    public function test_admin_receives_quick_actions_when_query_is_empty(): void
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/omnisearch')
            ->assertOk()
            ->assertJsonStructure([
                'query',
                'total_results',
                'results' => [
                    'actions',
                    'products',
                    'orders',
                    'users',
                    'categories',
                    'coupons',
                ],
            ]);

        $actions = $response->json('results.actions');
        $this->assertNotEmpty($actions);
        $this->assertStringContainsString('Thêm sản phẩm mới', $actions[0]['title']);
    }

    public function test_admin_can_search_products_by_name(): void
    {
        $category = Category::factory()->create();
        $brand = \App\Models\Brand::factory()->create();

        $product = Product::create([
            'name' => 'iPhone 16 Pro Max 512GB',
            'slug' => 'iphone-16-pro-max-512gb',
            'product_type' => 'phone',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'base_price' => 35000000,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/omnisearch?q=iPhone')
            ->assertOk();

        $products = $response->json('results.products');
        $this->assertCount(1, $products);
        $this->assertEquals('iPhone 16 Pro Max 512GB', $products[0]['title']);
        $this->assertEquals('product', $products[0]['type']);
    }

    public function test_admin_can_search_orders_by_code_and_phone(): void
    {
        $shipping = ShippingMethod::create([
            'name' => 'Giao hàng',
            'code' => 'STD_' . uniqid(),
            'fee' => 30000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->regularUser->id,
            'order_code' => 'MX-ORD-998877',
            'payment_method' => 'cod',
            'shipping_method_id' => $shipping->id,
            'shipping_method_code' => $shipping->code,
            'shipping_method_name' => $shipping->name,
            'subtotal' => 500000,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => 530000,
            'currency_code' => 'VND',
            'shipping_name' => 'Nguyen Van A',
            'shipping_phone' => '0988776655',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Cau Giay',
            'shipping_address_line' => '123 Pham Van Dong',
            'status' => 'pending',
        ]);

        // Search by order code
        $resCode = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/omnisearch?q=998877')
            ->assertOk();
        $this->assertCount(1, $resCode->json('results.orders'));
        $this->assertEquals('MX-ORD-998877', $resCode->json('results.orders.0.title'));

        // Search by phone
        $resPhone = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/omnisearch?q=0988776655')
            ->assertOk();
        $this->assertCount(1, $resPhone->json('results.orders'));
    }

    public function test_admin_can_search_coupons_by_code(): void
    {
        Coupon::create([
            'code' => 'SUPERVIP50',
            'discount_type' => 'percent',
            'discount_value' => 50,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/omnisearch?q=SUPERVIP')
            ->assertOk();

        $coupons = $response->json('results.coupons');
        $this->assertCount(1, $coupons);
        $this->assertEquals('SUPERVIP50', $coupons[0]['title']);
    }
}
