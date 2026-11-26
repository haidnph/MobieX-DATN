<?php

namespace Tests\Feature\Returns;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerReturnRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $otherCustomer;

    private Order $deliveredOrder;

    private OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = User::factory()->create(['is_active' => true]);
        $this->otherCustomer = User::factory()->create(['is_active' => true]);

        $shipping = ShippingMethod::create([
            'name' => 'Giao hàng tiêu chuẩn',
            'code' => 'STD',
            'fee' => 30000,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Điện thoại',
            'slug' => 'dien-thoai',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'is_active' => true,
        ]);

        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'name' => 'iPhone 15 Pro Max',
            'slug' => 'iphone-15-pro-max',
            'product_type' => 'phone',
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'IP15PM-256-TI',
            'price' => 30000000,
            'status' => 'active',
        ]);

        $this->deliveredOrder = Order::create([
            'user_id' => $this->customer->id,
            'order_code' => 'MX-ORD-TEST-01',
            'shipping_method_id' => $shipping->id,
            'shipping_method_code' => $shipping->code,
            'shipping_method_name' => $shipping->name,
            'subtotal' => 60000000,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => 60030000,
            'currency_code' => 'VND',
            'shipping_name' => 'Nguyễn Văn Test',
            'shipping_phone' => '0987654321',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dịch Vọng',
            'shipping_address_line' => '123 Đường Cầu Giấy',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'delivered',
            'delivered_at' => now()->subDay(),
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->deliveredOrder->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => 'iPhone 15 Pro Max',
            'variant_name' => 'Titan tự nhiên 256GB',
            'sku' => 'IP15PM-256-TI',
            'price' => 30000000,
            'quantity' => 2,
            'total' => 60000000,
        ]);
    }

    public function test_customer_can_create_return_request_for_delivered_order_with_refund(): void
    {
        $payload = [
            'order_id' => $this->deliveredOrder->id,
            'reason' => 'Sản phẩm bị lỗi kỹ thuật / không hoạt động',
            'return_type' => 'refund',
            'description' => 'Màn hình bị sọc xanh khi bật nguồn lần đầu',
            'customer_note' => 'Nhờ cửa hàng hỗ trợ kiểm tra sớm',
            'bank_name' => 'Vietcombank',
            'bank_account_number' => '0123456789',
            'bank_account_name' => 'NGUYEN VAN TEST',
            'items' => [
                [
                    'order_item_id' => $this->orderItem->id,
                    'quantity' => 1,
                ],
            ],
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/returns', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Đã gửi yêu cầu đổi trả thành công. Cửa hàng sẽ thẩm định và phản hồi trong thời gian sớm nhất.',
                'data' => [
                    'order_id' => $this->deliveredOrder->id,
                    'status' => 'pending',
                    'return_type' => 'refund',
                    'refund_status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('return_requests', [
            'order_id' => $this->deliveredOrder->id,
            'user_id' => $this->customer->id,
            'status' => 'pending',
            'return_type' => 'refund',
            'refund_amount' => 30000000,
            'bank_account_number' => '0123456789',
        ]);

        $this->assertDatabaseHas('return_request_items', [
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
        ]);

        $this->assertDatabaseHas('return_histories', [
            'status' => 'pending',
            'user_id' => $this->customer->id,
        ]);
    }

    public function test_customer_cannot_request_return_for_order_not_delivered(): void
    {
        $this->deliveredOrder->update(['status' => 'processing']);

        $payload = [
            'order_id' => $this->deliveredOrder->id,
            'reason' => 'Sản phẩm bị lỗi',
            'return_type' => 'refund',
            'items' => [
                ['order_item_id' => $this->orderItem->id, 'quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/returns', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['order_id']);
    }

    public function test_customer_cannot_request_return_for_another_users_order(): void
    {
        $payload = [
            'order_id' => $this->deliveredOrder->id,
            'reason' => 'Sản phẩm bị lỗi',
            'return_type' => 'refund',
            'items' => [
                ['order_item_id' => $this->orderItem->id, 'quantity' => 1],
            ],
        ];

        $response = $this->actingAs($this->otherCustomer, 'sanctum')
            ->postJson('/api/returns', $payload);

        $response->assertStatus(403);
    }

    public function test_customer_cannot_request_more_quantity_than_purchased(): void
    {
        $payload = [
            'order_id' => $this->deliveredOrder->id,
            'reason' => 'Sản phẩm bị lỗi',
            'return_type' => 'replacement',
            'description' => 'Đổi sang máy khác',
            'items' => [
                ['order_item_id' => $this->orderItem->id, 'quantity' => 3], // Only purchased 2
            ],
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->postJson('/api/returns', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_customer_can_cancel_pending_return_request(): void
    {
        $return = ReturnRequest::create([
            'return_code' => 'RTN-TEST-CANCEL-01',
            'order_id' => $this->deliveredOrder->id,
            'user_id' => $this->customer->id,
            'status' => 'pending',
            'reason' => 'Đổi ý',
            'return_type' => 'refund',
        ]);

        $response = $this->actingAs($this->customer, 'sanctum')
            ->patchJson("/api/returns/{$return->id}/cancel");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Đã hủy yêu cầu đổi trả thành công.',
                'data' => [
                    'id' => $return->id,
                    'status' => 'cancelled',
                ],
            ]);

        $this->assertDatabaseHas('return_requests', [
            'id' => $return->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_customer_can_submit_shipping_when_approved(): void
    {
        $return = ReturnRequest::create([
            'return_code' => 'RTN-TEST-SHIP-01',
            'order_id' => $this->deliveredOrder->id,
            'user_id' => $this->customer->id,
            'status' => 'approved',
            'reason' => 'Hàng lỗi',
            'return_type' => 'refund',
        ]);

        $payload = [
            'shipping_carrier' => 'Giao Hàng Tiết Kiệm',
            'return_tracking_code' => 'GHTK123456789',
        ];

        $response = $this->actingAs($this->customer, 'sanctum')
            ->patchJson("/api/returns/{$return->id}/shipping", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Đã cập nhật thông tin gửi hàng về cửa hàng.',
                'data' => [
                    'id' => $return->id,
                    'status' => 'shipping_back',
                    'shipping_carrier' => 'Giao Hàng Tiết Kiệm',
                    'return_tracking_code' => 'GHTK123456789',
                ],
            ]);

        $this->assertDatabaseHas('return_requests', [
            'id' => $return->id,
            'status' => 'shipping_back',
            'shipping_carrier' => 'Giao Hàng Tiết Kiệm',
            'return_tracking_code' => 'GHTK123456789',
        ]);
    }

    public function test_customer_can_view_own_return_list_and_details(): void
    {
        $return = ReturnRequest::create([
            'return_code' => 'RTN-TEST-VIEW-01',
            'order_id' => $this->deliveredOrder->id,
            'user_id' => $this->customer->id,
            'status' => 'pending',
            'reason' => 'Lỗi kết nối',
            'return_type' => 'refund',
        ]);

        $listRes = $this->actingAs($this->customer, 'sanctum')
            ->getJson('/api/returns');

        $listRes->assertStatus(200)
            ->assertJsonPath('data.0.id', $return->id);

        $detailRes = $this->actingAs($this->customer, 'sanctum')
            ->getJson("/api/returns/{$return->id}");

        $detailRes->assertStatus(200)
            ->assertJsonPath('data.return_code', 'RTN-TEST-VIEW-01');

        // Other customer cannot view this return
        $forbiddenRes = $this->actingAs($this->otherCustomer, 'sanctum')
            ->getJson("/api/returns/{$return->id}");

        $forbiddenRes->assertStatus(404);
    }
}
