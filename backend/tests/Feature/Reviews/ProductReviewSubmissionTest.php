<?php

namespace Tests\Feature\Reviews;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductReviewSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private Product $product;

    private ProductVariant $variant;

    private ShippingMethod $shippingMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['is_active' => true]);
        $this->otherUser = User::factory()->create(['is_active' => true]);

        $this->shippingMethod = ShippingMethod::create([
            'name' => 'Tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Phones',
            'slug' => 'phones',
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
            'sku' => 'S24U-256-GRY',
            'price' => 30000000,
            'is_active' => true,
        ]);
    }

    private function createOrderWithItem(User $customer, string $orderStatus = 'delivered'): OrderItem
    {
        $order = Order::create([
            'user_id' => $customer->id,
            'order_code' => 'MX-ORD-'.uniqid(),
            'shipping_method_id' => $this->shippingMethod->id,
            'shipping_method_code' => $this->shippingMethod->code,
            'shipping_method_name' => $this->shippingMethod->name,
            'subtotal' => 30000000,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => 30030000,
            'currency_code' => 'VND',
            'shipping_name' => $customer->name,
            'shipping_phone' => '0912345678',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dich Vong',
            'shipping_address_line' => '123 Tran Thai Tong',
            'payment_method' => 'cod',
            'status' => $orderStatus,
        ]);

        return OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $this->variant->id,
            'product_name' => $this->product->name,
            'variant_name' => 'Gray 256GB',
            'sku' => $this->variant->sku,
            'price' => 30000000,
            'quantity' => 1,
        ]);
    }

    public function test_user_can_submit_review_for_delivered_item(): void
    {
        $orderItem = $this->createOrderWithItem($this->user, 'delivered');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/reviews", [
                'order_item_id' => $orderItem->id,
                'rating' => 5,
                'title' => 'Dung rat thich',
                'content' => 'May muot ma, pin trau, man hinh dep tuyet voi!',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Gửi đánh giá thành công. Đánh giá của bạn đang chờ kiểm duyệt.',
                'data' => [
                    'product_id' => $this->product->id,
                    'user_id' => $this->user->id,
                    'order_item_id' => $orderItem->id,
                    'rating' => 5,
                    'status' => 'pending',
                ],
            ]);

        $this->assertDatabaseHas('reviews', [
            'order_item_id' => $orderItem->id,
            'product_id' => $this->product->id,
            'rating' => 5,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_review_if_order_is_not_delivered(): void
    {
        $orderItem = $this->createOrderWithItem($this->user, 'shipping');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/reviews", [
                'order_item_id' => $orderItem->id,
                'rating' => 5,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Chỉ có thể đánh giá sản phẩm khi đơn hàng đã được giao thành công (delivered).',
            ]);
    }

    public function test_cannot_review_other_users_order_item(): void
    {
        $orderItemOfOther = $this->createOrderWithItem($this->otherUser, 'delivered');

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/reviews", [
                'order_item_id' => $orderItemOfOther->id,
                'rating' => 4,
            ]);

        $response->assertStatus(403);
    }

    public function test_cannot_review_same_order_item_twice(): void
    {
        $orderItem = $this->createOrderWithItem($this->user, 'delivered');

        // First review
        $res1 = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/reviews", [
                'order_item_id' => $orderItem->id,
                'rating' => 5,
            ]);
        $res1->assertStatus(201);

        // Second review attempt
        $res2 = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/products/{$this->product->id}/reviews", [
                'order_item_id' => $orderItem->id,
                'rating' => 4,
            ]);

        $res2->assertStatus(422)
            ->assertJson([
                'message' => 'Món hàng này đã được đánh giá.',
            ]);
    }
}
