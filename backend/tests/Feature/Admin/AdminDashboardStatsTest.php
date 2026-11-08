<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $regularUser;

    private ShippingMethod $shippingMethod;

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

        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);
    }

    private function createOrder(string $paymentStatus, string $status, float $amount, ?string $createdAt = null): Order
    {
        $order = Order::create([
            'user_id' => $this->regularUser->id,
            'order_code' => 'MX-ORD-'.uniqid(),
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => $this->shippingMethod->code,
            'shipping_method_name' => $this->shippingMethod->name,
            'subtotal' => $amount,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total_amount' => $amount,
            'currency_code' => 'VND',
            'shipping_name' => 'Test',
            'shipping_phone' => '0912345678',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dich Vong',
            'shipping_address_line' => '123 Tran Thai Tong',
            'payment_method' => 'vnpay',
            'payment_status' => $paymentStatus,
            'status' => $status,
            'created_at' => $createdAt ? now()->parse($createdAt) : now(),
        ]);

        return $order;
    }

    public function test_regular_user_cannot_view_dashboard(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(403);
    }

    public function test_admin_can_view_settled_revenue_and_stock_alerts(): void
    {
        // 1. Paid & confirmed order: 10,000,000 -> Should count towards settled revenue
        $this->createOrder('paid', 'confirmed', 10000000);

        // 2. Paid & delivered order: 5,000,000 -> Should count
        $this->createOrder('paid', 'delivered', 5000000);

        // 3. Paid but cancelled order: 3,000,000 -> Should NOT count towards revenue
        $this->createOrder('paid', 'cancelled', 3000000);

        // 4. Pending payment order: 2,000,000 -> Should NOT count towards revenue
        $this->createOrder('pending', 'pending', 2000000);

        // Setup products and inventories
        $category = Category::create(['name' => 'Audio', 'slug' => 'audio', 'is_active' => true]);
        $brand = Brand::create(['name' => 'Sony', 'slug' => 'sony', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'accessory',
            'name' => 'Tai nghe Sony',
            'slug' => 'tai-nghe-sony',
            'status' => 'active',
        ]);

        $v1 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SN-1',
            'option_signature' => 'color:black',
            'price' => 1000000,
            'is_active' => true,
        ]);
        // Low stock: on_hand=3, reserved=0, min_stock=5 -> available=3 <= 5 -> ALERT
        Inventory::create([
            'product_variant_id' => $v1->id,
            'on_hand_quantity' => 3,
            'reserved_quantity' => 0,
            'min_stock' => 5,
        ]);

        $v2 = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SN-2',
            'option_signature' => 'color:white',
            'price' => 2000000,
            'is_active' => true,
        ]);
        // Healthy stock: on_hand=20, reserved=2, min_stock=5 -> available=18 > 5 -> OK
        Inventory::create([
            'product_variant_id' => $v2->id,
            'on_hand_quantity' => 20,
            'reserved_quantity' => 2,
            'min_stock' => 5,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/dashboard/stats');

        $response->assertStatus(200);

        $data = $response->json('data');

        // Settled revenue = 10M + 5M = 15,000,000
        $this->assertEquals(15000000, $data['total_revenue']);
        $this->assertEquals(1, $data['pending_orders_count']);
        $this->assertEquals(1, $data['cancelled_orders_count']);
        $this->assertEquals(4, $data['total_orders_count']);
        $this->assertEquals(1, $data['low_stock_count']);
        $this->assertCount(1, $data['low_stock_items']);
        $this->assertEquals('SN-1', $data['low_stock_items'][0]['sku']);
    }

    public function test_admin_can_view_revenue_chart_data(): void
    {
        $this->createOrder('paid', 'confirmed', 5000000);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/dashboard/revenue-chart?days=7');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'labels',
                    'datasets' => [
                        '*' => ['label', 'data'],
                    ],
                    'items' => [
                        '*' => ['date', 'revenue', 'orders'],
                    ],
                ],
            ]);

        $items = $response->json('data.items');
        $this->assertCount(7, $items);
    }
}
