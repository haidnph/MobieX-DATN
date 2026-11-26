<?php

namespace Tests\Feature\Returns;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnItem;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReturnManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $customer;

    private Order $order;

    private ProductVariant $variant;

    private OrderItem $orderItem;

    private ReturnRequest $returnRequest;

    private ReturnItem $returnItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->customer = User::factory()->create(['is_active' => true]);

        // Setup RBAC permissions with required slug field
        $ordersViewPerm = Permission::firstOrCreate(
            ['slug' => 'orders.view'],
            ['name' => 'Xem đơn hàng', 'description' => 'Xem đơn hàng']
        );
        $ordersManagePerm = Permission::firstOrCreate(
            ['slug' => 'orders.manage'],
            ['name' => 'Quản lý đơn hàng', 'description' => 'Quản lý đơn hàng']
        );

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Quản trị viên', 'description' => 'Admin role', 'is_system' => true]
        );
        $adminRole->permissions()->syncWithoutDetaching([$ordersViewPerm->id, $ordersManagePerm->id]);
        $this->admin->roles()->attach($adminRole->id);

        $shipping = ShippingMethod::create([
            'name' => 'Standard',
            'code' => 'STD',
            'fee' => 0,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Phụ kiện',
            'slug' => 'phu-kien',
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
            'name' => 'Tai nghe AirPods Pro',
            'slug' => 'tai-nghe-airpods-pro',
            'product_type' => 'accessory',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'APP-2ND-GEN',
            'price' => 5000000,
            'status' => 'active',
        ]);

        Inventory::create([
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $this->order = Order::create([
            'user_id' => $this->customer->id,
            'order_code' => 'MX-ORD-RET-TEST',
            'shipping_method_id' => $shipping->id,
            'shipping_method_code' => $shipping->code,
            'shipping_method_name' => $shipping->name,
            'subtotal' => 5000000,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total_amount' => 5000000,
            'currency_code' => 'VND',
            'shipping_name' => 'Khách hàng test',
            'shipping_phone' => '0912345678',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dịch Vọng',
            'shipping_address_line' => '456 Cầu Giấy',
            'payment_method' => 'vnpay',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'delivered_at' => now()->subDays(2),
        ]);

        Payment::create([
            'order_id' => $this->order->id,
            'transaction_code' => 'PAY-RET-TEST-01',
            'idempotency_key' => 'vnpay-ret-'.$this->order->id,
            'method' => 'vnpay',
            'amount' => 5000000,
            'currency_code' => 'VND',
            'status' => 'success',
            'gateway_transaction_id' => '15888999',
            'paid_at' => now()->subDays(2),
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Tai nghe AirPods Pro',
            'variant_name' => 'Mặc định',
            'sku' => 'APP-2ND-GEN',
            'price' => 5000000,
            'quantity' => 1,
            'total' => 5000000,
        ]);

        $this->returnRequest = ReturnRequest::create([
            'return_code' => 'RTN-ADM-TEST-01',
            'order_id' => $this->order->id,
            'user_id' => $this->customer->id,
            'status' => 'pending',
            'reason' => 'Lỗi kết nối bluetooth',
            'return_type' => 'refund',
            'refund_amount' => 5000000,
            'refund_status' => 'pending',
            'description' => 'Bị ngắt kết nối liên tục khi nghe nhạc',
        ]);

        $this->returnItem = ReturnItem::create([
            'return_request_id' => $this->returnRequest->id,
            'order_item_id' => $this->orderItem->id,
            'quantity' => 1,
            'refund_amount' => 5000000,
        ]);
    }

    public function test_admin_can_approve_pending_return_request(): void
    {
        $payload = [
            'admin_note' => 'Cửa hàng chấp thuận đổi trả. Vui lòng gửi về kho Cầu Giấy.',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/returns/{$this->returnRequest->id}/approve", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->returnRequest->id,
                    'status' => 'approved',
                ],
            ]);

        $this->assertDatabaseHas('return_requests', [
            'id' => $this->returnRequest->id,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('return_histories', [
            'return_request_id' => $this->returnRequest->id,
            'status' => 'approved',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_admin_can_reject_return_request_with_mandatory_note(): void
    {
        // Rejection requires note
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/returns/{$this->returnRequest->id}/reject", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['admin_note']);

        // With note
        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/returns/{$this->returnRequest->id}/reject", [
                'admin_note' => 'Sản phẩm không thuộc diện bảo hành do rơi vỡ ngoại lực.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->returnRequest->id,
                    'status' => 'rejected',
                ],
            ]);

        $this->assertDatabaseHas('return_requests', [
            'id' => $this->returnRequest->id,
            'status' => 'rejected',
        ]);
    }

    public function test_admin_can_confirm_receipt_and_inspect_items(): void
    {
        $this->returnRequest->update(['status' => 'approved']);

        // 1. Confirm Receive
        $receiveRes = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/returns/{$this->returnRequest->id}/receive", [
                'admin_note' => 'Kiện hàng nguyên vẹn tem niêm phong.',
            ]);

        $receiveRes->assertStatus(200)
            ->assertJsonPath('data.status', 'received');

        // 2. Inspect Items
        $inspectPayload = [
            'admin_note' => 'Tai nghe ngoại hình như mới, hộp đầy đủ dây cáp. Đủ điều kiện nhập kho lại.',
            'items' => [
                [
                    'id' => $this->returnItem->id,
                    'inspection_condition' => 'good',
                    'inspection_note' => 'Đã test kỹ thuật OK',
                    'is_restocked' => true,
                ],
            ],
        ];

        $inspectRes = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/returns/{$this->returnRequest->id}/inspect", $inspectPayload);

        $inspectRes->assertStatus(200)
            ->assertJsonPath('data.status', 'inspecting');

        $this->assertDatabaseHas('return_request_items', [
            'id' => $this->returnItem->id,
            'inspection_condition' => 'good',
            'is_restocked' => true,
        ]);
    }

    public function test_admin_can_complete_refund_request_restocking_inventory_and_updating_order(): void
    {
        $this->returnRequest->update(['status' => 'inspecting']);
        $this->returnItem->update([
            'inspection_condition' => 'good',
            'is_restocked' => true,
        ]);

        $initialStock = Inventory::where('product_variant_id', $this->variant->id)->value('on_hand_quantity');

        $payload = [
            'refund_amount' => 5000000,
            'refund_method' => 'bank_transfer',
            'refund_transaction_id' => 'FT2611888999',
            'admin_note' => 'Đã chuyển khoản hoàn tiền theo STK của khách hàng.',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/returns/{$this->returnRequest->id}/complete", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Đã hoàn tất xác nhận hoàn tiền và đồng bộ trạng thái đơn hàng.',
                'data' => [
                    'id' => $this->returnRequest->id,
                    'status' => 'completed',
                    'refund_status' => 'refunded',
                    'refund_amount' => 5000000,
                    'refund_transaction_id' => 'FT2611888999',
                ],
            ]);

        // Order payment status must be refunded
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'refunded',
        ]);

        // Online payment record must be marked refunded
        $this->assertDatabaseHas('payments', [
            'order_id' => $this->order->id,
            'status' => 'refunded',
            'refund_transaction_id' => 'FT2611888999',
        ]);

        // Inventory must be restocked (+1)
        $newStock = Inventory::where('product_variant_id', $this->variant->id)->value('on_hand_quantity');
        $this->assertEquals($initialStock + 1, $newStock);

        $this->assertDatabaseHas('inventory_transactions', [
            'product_variant_id' => $this->variant->id,
            'type' => 'import',
            'quantity_delta' => 1,
            'reference_type' => 'return_request',
            'reference_id' => $this->returnRequest->id,
        ]);

        // Item marked restocked
        $this->assertDatabaseHas('return_request_items', [
            'id' => $this->returnItem->id,
            'is_restocked' => true,
        ]);
        $this->assertNotNull($this->returnItem->fresh()->restocked_at);
    }

    public function test_admin_can_complete_replacement_request_with_new_tracking_code(): void
    {
        $this->returnRequest->update([
            'return_type' => 'replacement',
            'status' => 'inspecting',
        ]);

        $payload = [
            'replacement_carrier' => 'Viettel Post',
            'replacement_tracking_code' => 'VTP999888777',
            'admin_note' => 'Đã gửi máy mới nguyên hộp cho khách hàng.',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/returns/{$this->returnRequest->id}/complete", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Đã hoàn tất quy trình đổi hàng và cập nhật vận đơn gửi mới.',
                'data' => [
                    'id' => $this->returnRequest->id,
                    'status' => 'completed',
                    'replacement_carrier' => 'Viettel Post',
                    'replacement_tracking_code' => 'VTP999888777',
                ],
            ]);

        $this->assertDatabaseHas('return_requests', [
            'id' => $this->returnRequest->id,
            'status' => 'completed',
            'replacement_carrier' => 'Viettel Post',
            'replacement_tracking_code' => 'VTP999888777',
        ]);

        // Order payment status should NOT be refunded for replacements
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_admin_can_list_and_filter_return_requests(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->getJson('/api/admin/returns?status=pending');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.id', $this->returnRequest->id);

        $detailRes = $this->actingAs($this->admin, 'sanctum')
            ->getJson("/api/admin/returns/{$this->returnRequest->id}");

        $detailRes->assertStatus(200)
            ->assertJsonPath('data.return_code', $this->returnRequest->return_code);
    }

    public function test_admin_cannot_refund_amount_exceeding_order_total(): void
    {
        $this->returnRequest->update(['status' => 'inspecting']);

        $payload = [
            'refund_amount' => 99999999999, // Exceeds order total 5,000,000
            'refund_method' => 'bank_transfer',
            'refund_transaction_id' => 'FT_OVERFLOW',
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->patchJson("/api/admin/returns/{$this->returnRequest->id}/complete", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['refund_amount']);
    }
}
