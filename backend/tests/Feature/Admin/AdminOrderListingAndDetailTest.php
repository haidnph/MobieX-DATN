<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderListingAndDetailTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $regularUser;

    private ShippingMethod $shippingMethod;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        $permission = Permission::firstOrCreate(
            ['slug' => 'orders.view'],
            ['name' => 'Xem đơn hàng', 'description' => 'Xem đơn hàng']
        );

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Quản trị viên', 'description' => 'Admin role', 'is_system' => true]
        );
        $adminRole->permissions()->syncWithoutDetaching([$permission->id]);

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->regularUser = User::factory()->create(['is_active' => true]);

        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Audio',
            'slug' => 'audio',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Sony',
            'slug' => 'sony',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'accessory',
            'name' => 'Sony WH-1000XM5',
            'slug' => 'sony-wh-1000xm5',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'WH1000XM5-BLK',
            'option_signature' => 'color:black',
            'price' => 8000000,
            'is_active' => true,
        ]);
    }

    private function createOrder(User $customer, string $code, string $status = 'pending', string $phone = '0912345678'): Order
    {
        $order = Order::create([
            'user_id' => $customer->id,
            'order_code' => $code,
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => $this->shippingMethod->code,
            'shipping_method_name' => $this->shippingMethod->name,
            'subtotal' => 8000000,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => 8030000,
            'currency_code' => 'VND',
            'shipping_name' => $customer->name,
            'shipping_phone' => $phone,
            'shipping_address_line' => '12 Nguyễn Trãi, Hà Nội',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Thượng Đình',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => $status,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->variant->product_id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Sony WH-1000XM5',
            'variant_name' => 'Black',
            'sku' => $this->variant->sku,
            'price' => 8000000,
            'quantity' => 1,
        ]);

        return $order;
    }

    public function test_user_without_permission_is_forbidden_from_admin_orders(): void
    {
        $this->createOrder($this->regularUser, 'MX-TEST-001');

        $response = $this->actingAs($this->regularUser)->getJson('/api/admin/orders');
        $response->assertStatus(403);
    }

    public function test_admin_can_list_and_filter_orders(): void
    {
        $customerA = User::factory()->create(['is_active' => true]);
        $customerB = User::factory()->create(['is_active' => true]);

        $order1 = $this->createOrder($customerA, 'MX-ORD-SEARCH1', 'pending', '0988111222');
        $order2 = $this->createOrder($customerB, 'MX-ORD-OTHER2', 'confirmed', '0977333444');

        // Admin lists all
        $response = $this->actingAs($this->adminUser)->getJson('/api/admin/orders');
        $response->assertOk();
        $this->assertCount(2, $response->json('data'));

        // Search by phone
        $searchResponse = $this->actingAs($this->adminUser)->getJson('/api/admin/orders?search=0988111222');
        $searchResponse->assertOk();
        $this->assertCount(1, $searchResponse->json('data'));
        $this->assertEquals('MX-ORD-SEARCH1', $searchResponse->json('data.0.order_code'));

        // Filter by status=confirmed
        $statusResponse = $this->actingAs($this->adminUser)->getJson('/api/admin/orders?status=confirmed');
        $statusResponse->assertOk();
        $this->assertCount(1, $statusResponse->json('data'));
        $this->assertEquals('MX-ORD-OTHER2', $statusResponse->json('data.0.order_code'));
    }

    public function test_admin_can_view_order_details(): void
    {
        $order = $this->createOrder($this->regularUser, 'MX-ORD-DETAIL');

        $response = $this->actingAs($this->adminUser)->getJson("/api/admin/orders/{$order->id}");

        $response->assertOk();
        $response->assertJsonPath('data.order_code', 'MX-ORD-DETAIL');
        $response->assertJsonPath('data.items.0.sku', $this->variant->sku);
    }
}
