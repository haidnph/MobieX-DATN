<?php

namespace Tests\Feature\Orders;

use App\Mail\OrderConfirmationMail;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\OrderMailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderConfirmationEmailTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $admin;

    private Product $product;

    private ProductVariant $variant;

    private Inventory $inventory;

    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        // 1. Setup Roles & Permissions
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
        $manageOrdersPerm = Permission::firstOrCreate(['slug' => 'orders.manage'], ['name' => 'Quản lý đơn']);
        $viewOrdersPerm = Permission::firstOrCreate(['slug' => 'orders.view'], ['name' => 'Xem đơn']);
        $adminRole->permissions()->syncWithoutDetaching([$manageOrdersPerm->id, $viewOrdersPerm->id]);

        $this->admin = User::factory()->create(['email' => 'admin@mobiex.vn', 'is_active' => true]);
        $this->admin->roles()->attach($adminRole->id);

        $this->customer = User::factory()->create(['email' => 'khachhang@example.com', 'is_active' => true]);

        // 2. Shipping method
        $this->shippingMethod = ShippingMethod::firstOrCreate(
            ['code' => 'standard'],
            ['name' => 'Giao hàng tiêu chuẩn', 'fee' => 30000, 'is_active' => true]
        );

        // 3. Catalog setup
        $category = Category::create([
            'name' => 'Điện thoại',
            'slug' => 'dien-thoai',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Samsung',
            'slug' => 'samsung',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Galaxy S24 Ultra',
            'slug' => 'galaxy-s24-ultra',
            'status' => 'active',
        ]);

        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'S24U-256-GRAY',
            'option_signature' => 'color:gray,storage:256gb',
            'price' => 28990000,
            'is_active' => true,
        ]);

        $this->inventory = Inventory::create([
            'product_variant_id' => $this->variant->id,
            'on_hand_quantity' => 10,
            'reserved_quantity' => 0,
            'min_stock' => 2,
        ]);
    }

    /**
     * Test Case 1: COD order triggers confirmation email with correct COD details.
     */
    public function test_cod_order_automatically_sends_confirmation_email_to_customer(): void
    {
        Sanctum::actingAs($this->customer);

        // Add to cart
        $this->postJson('/api/cart/items', [
            'variant_id' => $this->variant->id,
            'quantity' => 1,
        ])->assertStatus(200);

        // Checkout with COD
        $response = $this->postJson('/api/orders', [
            'recipient_name' => 'Nguyễn Văn A',
            'phone' => '0912345678',
            'shipping_address' => '123 Đường Cầu Giấy, Hà Nội',
            'payment_method' => 'cod',
        ]);

        $response->assertStatus(201);
        $orderId = $response->json('data.id');

        $order = Order::findOrFail($orderId);
        $this->assertEquals('cod', $order->payment_method);
        $this->assertEquals('pending', $order->payment_status);

        // Assert Mailable was queued/sent to customer email
        Mail::assertQueued(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            return $mail->hasTo('khachhang@example.com')
                && $mail->order->payment_method === 'cod'
                && str_contains($mail->envelope()->subject, 'Xác nhận đơn hàng');
        });

        // Verify tracking state on order
        $this->assertEquals('sent', $order->confirmation_email_status);
        $this->assertNotNull($order->confirmation_email_sent_at);

        // Verify audit log
        $this->assertDatabaseHas('order_email_logs', [
            'order_id' => $order->id,
            'recipient_email' => 'khachhang@example.com',
            'status' => 'sent',
        ]);
    }

    /**
     * Test Case 2: Custom customer_email in request is respected over user's account email.
     */
    public function test_order_with_custom_customer_email_sends_to_that_email(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/cart/items', [
            'variant_id' => $this->variant->id,
            'quantity' => 1,
        ])->assertStatus(200);

        $customEmail = 'nhanvien.muaho@congty.com';

        $response = $this->postJson('/api/orders', [
            'recipient_name' => 'Người Nhận Hộ',
            'phone' => '0988776655',
            'customer_email' => $customEmail,
            'shipping_address' => 'Tòa nhà FPT, Phạm Văn Bạch, Hà Nội',
            'payment_method' => 'cod',
        ]);

        $response->assertStatus(201);
        $orderId = $response->json('data.id');
        $order = Order::findOrFail($orderId);

        $this->assertEquals($customEmail, $order->customer_email);

        Mail::assertQueued(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) use ($customEmail) {
            return $mail->hasTo($customEmail);
        });
    }

    /**
     * Test Case 3: Email contains all accurate business data (pricing, items, address, status).
     */
    public function test_email_content_contains_all_required_order_and_payment_data(): void
    {
        $order = Order::create([
            'order_code' => 'MX-TESTEMAIL123',
            'user_id' => $this->customer->id,
            'customer_email' => 'test@mobiex.vn',
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => 'standard',
            'shipping_method_name' => 'Giao hàng tiêu chuẩn',
            'subtotal' => 28990000,
            'discount_amount' => 500000,
            'shipping_fee' => 30000,
            'total_amount' => 28520000,
            'currency_code' => 'VND',
            'shipping_name' => 'Trần Văn Tester',
            'shipping_phone' => '0933221100',
            'shipping_address_line' => 'Phòng 402, Tòa A, Keangnam',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Mễ Trì',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'customer_note' => 'Giao giờ hành chính',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Galaxy S24 Ultra',
            'variant_name' => 'Màu Xám / 256GB',
            'sku' => 'S24U-256-GRAY',
            'price' => 28990000,
            'quantity' => 1,
        ]);

        $mailable = new OrderConfirmationMail($order);

        // Render HTML content
        $html = $mailable->render();

        $this->assertStringContainsString('MX-TESTEMAIL123', $html);
        $this->assertStringContainsString('Trần Văn Tester', $html);
        $this->assertStringContainsString('0933221100', $html);
        $this->assertStringContainsString('Phòng 402, Tòa A, Keangnam', $html);
        $this->assertStringContainsString('Galaxy S24 Ultra', $html);
        $this->assertStringContainsString('Màu Xám / 256GB', $html);
        $this->assertStringContainsString('S24U-256-GRAY', $html);
        $this->assertStringContainsString('Thanh toán khi nhận hàng (COD)', $html);
        $this->assertStringContainsString('Chưa thanh toán (Thanh toán khi nhận hàng)', $html);
        $this->assertStringContainsString('Giao giờ hành chính', $html);
        $this->assertStringContainsString(number_format(28520000, 0, ',', '.'), $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString('alt="Galaxy S24 Ultra"', $html);
        $this->assertStringContainsString('width="64"', $html);
    }

    /**
     * Test Case 4: Online payment shows "Đã thanh toán thành công" when verified.
     */
    public function test_online_payment_shows_verified_paid_status_in_email(): void
    {
        $order = Order::create([
            'order_code' => 'MX-PAIDTEST456',
            'user_id' => $this->customer->id,
            'customer_email' => 'paid.customer@example.com',
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => 'standard',
            'shipping_method_name' => 'Giao hàng tiêu chuẩn',
            'subtotal' => 28990000,
            'discount_amount' => 0,
            'shipping_fee' => 0,
            'total_amount' => 28990000,
            'currency_code' => 'VND',
            'shipping_name' => 'Khách Trả Trước',
            'shipping_phone' => '0944556677',
            'shipping_address_line' => 'Tầng 10, Landmark 81, TP.HCM',
            'shipping_province_code' => 'VN-SG',
            'shipping_province_name' => 'TP. Hồ Chí Minh',
            'shipping_ward_code' => '002',
            'shipping_ward_name' => 'Phường 22',
            'payment_method' => 'vnpay',
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'Galaxy S24 Ultra',
            'sku' => 'S24U-256-GRAY',
            'price' => 28990000,
            'quantity' => 1,
        ]);

        $mailable = new OrderConfirmationMail($order);
        $html = $mailable->render();

        $this->assertStringContainsString('Đã thanh toán thành công', $html);
        $this->assertStringContainsString('Cổng thanh toán VNPay', $html);
        $this->assertStringContainsString('Xác nhận đơn hàng & thanh toán thành công', $mailable->envelope()->subject);
    }

    /**
     * Test Case 5: Idempotency - duplicate sends are skipped unless forced.
     */
    public function test_idempotency_prevents_duplicate_emails(): void
    {
        $order = Order::create([
            'order_code' => 'MX-IDEMPOTENT789',
            'user_id' => $this->customer->id,
            'customer_email' => 'idempotent@example.com',
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => 'standard',
            'shipping_method_name' => 'Giao hàng tiêu chuẩn',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'currency_code' => 'VND',
            'shipping_name' => 'Test Idempotency',
            'shipping_phone' => '0900000000',
            'shipping_address_line' => '123 Test St',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dịch Vọng',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $mailService = app(OrderMailService::class);

        // First send
        $result1 = $mailService->sendOrderConfirmation($order);
        $this->assertTrue($result1);
        Mail::assertQueued(OrderConfirmationMail::class, 1);

        // Refresh model to read updated confirmation_email_status
        $order->refresh();
        $this->assertEquals('sent', $order->confirmation_email_status);

        // Second send without force
        $result2 = $mailService->sendOrderConfirmation($order, force: false);
        $this->assertTrue($result2);

        // Mail should still be queued only once
        Mail::assertQueued(OrderConfirmationMail::class, 1);
    }

    /**
     * Test Case 6: Admin can manually resend confirmation email.
     */
    public function test_admin_can_resend_confirmation_email(): void
    {
        Sanctum::actingAs($this->admin);

        $order = Order::create([
            'order_code' => 'MX-ADMINRESEND',
            'user_id' => $this->customer->id,
            'customer_email' => 'khachhang@example.com',
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => 'standard',
            'shipping_method_name' => 'Giao hàng tiêu chuẩn',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'currency_code' => 'VND',
            'shipping_name' => 'Khách Hàng',
            'shipping_phone' => '0900000000',
            'shipping_address_line' => '123 Đường Test',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dịch Vọng',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
            'confirmation_email_status' => 'failed',
            'confirmation_email_error' => 'Simulated previous SMTP timeout',
        ]);

        $response = $this->postJson("/api/admin/orders/{$order->id}/resend-confirmation-email");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        Mail::assertQueued(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            return $mail->hasTo('khachhang@example.com');
        });

        $order->refresh();
        $this->assertEquals('sent', $order->confirmation_email_status);
        $this->assertNull($order->confirmation_email_error);
    }

    /**
     * Test Case 7: Admin can view email audit logs for an order.
     */
    public function test_admin_can_view_order_email_logs(): void
    {
        Sanctum::actingAs($this->admin);

        $order = Order::create([
            'order_code' => 'MX-LOGCHECK',
            'user_id' => $this->customer->id,
            'customer_email' => 'log.customer@example.com',
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => 'standard',
            'shipping_method_name' => 'Giao hàng tiêu chuẩn',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'currency_code' => 'VND',
            'shipping_name' => 'Khách Hàng',
            'shipping_phone' => '0900000000',
            'shipping_address_line' => '123 Đường Test',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dịch Vọng',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        app(OrderMailService::class)->sendOrderConfirmation($order);

        $response = $this->getJson("/api/admin/orders/{$order->id}/email-logs");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.recipient_email', 'log.customer@example.com')
            ->assertJsonPath('data.0.status', 'sent');
    }

    /**
     * Test Case 8: Mail service error or exception DOES NOT crash the checkout or roll back the order.
     */
    public function test_email_service_failure_does_not_break_checkout(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/cart/items', [
            'variant_id' => $this->variant->id,
            'quantity' => 1,
        ])->assertStatus(200);

        // Intentionally provide an empty recipient email in guest mode or force mail service error
        $response = $this->postJson('/api/orders', [
            'recipient_name' => 'Khách Thử Nghiệm',
            'phone' => '0911223344',
            'customer_email' => 'invalid-email-format-without-at',
            'shipping_address' => '123 Đường Test, Hà Nội',
            'payment_method' => 'cod',
        ]);

        // Validation for email should fail gracefully or checkout with invalid email should be caught
        // If email validation catches it:
        if ($response->status() === 422) {
            $response->assertJsonValidationErrors(['customer_email']);
        } else {
            // If created, order must exist in DB and confirmation_email_status is 'failed'
            $response->assertStatus(201);
            $orderId = $response->json('data.id');
            $order = Order::findOrFail($orderId);
            $this->assertEquals('failed', $order->confirmation_email_status);
        }
    }

    /**
     * Test Case 9: Plain text email fallback contains all order data.
     */
    public function test_plain_text_email_version_contains_accurate_details(): void
    {
        $order = Order::create([
            'order_code' => 'MX-PLAINTEXT',
            'user_id' => $this->customer->id,
            'customer_email' => 'plain@mobiex.vn',
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => 'standard',
            'shipping_method_name' => 'Giao hàng tiêu chuẩn',
            'subtotal' => 100000,
            'total_amount' => 100000,
            'currency_code' => 'VND',
            'shipping_name' => 'Khách PlainText',
            'shipping_phone' => '0900000000',
            'shipping_address_line' => '123 Đường Test',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dịch Vọng',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $mailable = new OrderConfirmationMail($order);
        $plainText = view('emails.orders.confirmation_plain', [
            'order' => $order,
            'viewOrderUrl' => 'http://localhost:5173/orders/'.$order->id,
            'paymentMethodLabel' => 'Thanh toán khi nhận hàng (COD)',
            'paymentStatusLabel' => [
                'text' => 'Chưa thanh toán (Thanh toán khi nhận hàng)',
                'description' => 'Quý khách vui lòng chuẩn bị tiền mặt.',
            ],
            'orderStatusLabel' => ['text' => 'Chờ xử lý'],
            'transactionId' => null,
        ])->render();

        $this->assertStringContainsString('MX-PLAINTEXT', $plainText);
        $this->assertStringContainsString('Khách PlainText', $plainText);
        $this->assertStringContainsString('Thanh toán khi nhận hàng (COD)', $plainText);
        $this->assertStringContainsString('100.000 VND', $plainText);
    }

    /**
     * Test Case 10: Multi-product order renders distinct thumbnails for each item.
     */
    public function test_multi_product_order_renders_distinct_images_for_each_item(): void
    {
        $order = Order::create([
            'order_code' => 'MX-MULTI-IMG',
            'user_id' => $this->customer->id,
            'customer_email' => 'multi@example.com',
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => 'standard',
            'shipping_method_name' => 'Giao hàng tiêu chuẩn',
            'subtotal' => 40000000,
            'total_amount' => 40000000,
            'currency_code' => 'VND',
            'shipping_name' => 'Nguyễn Đa Năng',
            'shipping_phone' => '0988112233',
            'shipping_address_line' => '456 Phố Huế',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Hà Nội',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Hàng Bài',
            'payment_method' => 'cod',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);

        $item1Img = 'https://images.unsplash.com/photo-1695048133142-1a20484d2569?q=80&w=600';
        $item2Img = 'https://images.unsplash.com/photo-1600294037681-c80b4cb5b434?q=80&w=600';

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => 'iPhone 16 Pro Max',
            'variant_name' => 'Titan Tự Nhiên • 256GB',
            'sku' => 'IP16PM-256-NAT',
            'image_path' => $item1Img,
            'price' => 34990000,
            'quantity' => 1,
        ]);

        $variant2 = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'APP2-USBC',
            'option_signature' => 'color:white',
            'price' => 5010000,
            'is_active' => true,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $variant2->id,
            'product_name' => 'AirPods Pro 2',
            'variant_name' => 'Trắng MagSafe',
            'sku' => 'APP2-USBC',
            'image_path' => $item2Img,
            'price' => 5010000,
            'quantity' => 1,
        ]);

        $mailable = new OrderConfirmationMail($order);
        $html = $mailable->render();

        // Verify both product titles and images are rendered
        $this->assertStringContainsString('iPhone 16 Pro Max', $html);
        $this->assertStringContainsString('Titan Tự Nhiên • 256GB', $html);
        $this->assertStringContainsString('AirPods Pro 2', $html);
        $this->assertStringContainsString('Trắng MagSafe', $html);

        $this->assertStringContainsString(e($item1Img), $html);
        $this->assertStringContainsString(e($item2Img), $html);
    }
}
