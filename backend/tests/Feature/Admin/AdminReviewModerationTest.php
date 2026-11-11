<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $regularUser;

    private Product $product;

    private OrderItem $orderItem;

    protected function setUp(): void
    {
        parent::setUp();

        $viewPerm = Permission::firstOrCreate(
            ['slug' => 'reviews.view'],
            ['name' => 'Xem đánh giá', 'description' => 'Xem đánh giá']
        );

        $moderatePerm = Permission::firstOrCreate(
            ['slug' => 'reviews.moderate'],
            ['name' => 'Kiểm duyệt đánh giá', 'description' => 'Kiểm duyệt đánh giá']
        );

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Quản trị viên', 'description' => 'Admin role', 'is_system' => true]
        );
        $adminRole->permissions()->syncWithoutDetaching([$viewPerm->id, $moderatePerm->id]);

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->regularUser = User::factory()->create(['is_active' => true]);

        $category = Category::create([
            'name' => 'Phones',
            'slug' => 'phones',
            'is_active' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'iPhone 15 Pro Max',
            'slug' => 'iphone-15-pro-max',
            'status' => 'active',
        ]);

        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'IP15PM-256',
            'price' => 30000000,
            'is_active' => true,
        ]);

        $shippingMethod = ShippingMethod::create([
            'name' => 'Tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);

        $order = Order::create([
            'user_id' => $this->regularUser->id,
            'order_code' => 'MX-ORD-REV-1',
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_code' => $shippingMethod->code,
            'shipping_method_name' => $shippingMethod->name,
            'subtotal' => 30000000,
            'discount_amount' => 0,
            'shipping_fee' => 30000,
            'total_amount' => 30030000,
            'currency_code' => 'VND',
            'shipping_name' => $this->regularUser->name,
            'shipping_phone' => '0987654321',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dich Vong',
            'shipping_address_line' => '123 Pham Van Dong',
            'payment_method' => 'cod',
            'status' => 'delivered',
        ]);

        $this->orderItem = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $this->product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $this->product->name,
            'variant_name' => 'Natural Titanium',
            'sku' => $variant->sku,
            'price' => 30000000,
            'quantity' => 1,
        ]);
    }

    public function test_only_approved_reviews_are_visible_publicly(): void
    {
        // 1. Pending review
        Review::create([
            'product_id' => $this->product->id,
            'user_id' => $this->regularUser->id,
            'order_item_id' => $this->orderItem->id,
            'rating' => 5,
            'title' => 'Pending review',
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/products/{$this->product->id}/reviews");
        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }

    public function test_admin_can_approve_review_and_it_becomes_public(): void
    {
        $review = Review::create([
            'product_id' => $this->product->id,
            'user_id' => $this->regularUser->id,
            'order_item_id' => $this->orderItem->id,
            'rating' => 5,
            'title' => 'Awesome phone',
            'status' => 'pending',
        ]);

        // Admin approves
        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/admin/reviews/{$review->id}/status", [
                'status' => 'approved',
                'moderation_note' => 'Đánh giá chân thật',
                'admin_reply' => 'Cảm ơn bạn đã tin tưởng MobieX!',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        $this->assertEquals('approved', $review->fresh()->status);
        $this->assertEquals($this->adminUser->id, $review->fresh()->moderated_by_user_id);
        $this->assertNotNull($review->fresh()->moderated_at);

        // Now public should see 1 review
        $publicRes = $this->getJson("/api/products/{$this->product->id}/reviews");
        $publicRes->assertStatus(200);
        $this->assertCount(1, $publicRes->json('data'));
        $this->assertEquals('Awesome phone', $publicRes->json('data.0.title'));
    }

    public function test_unauthorized_user_cannot_moderate_reviews(): void
    {
        $review = Review::create([
            'product_id' => $this->product->id,
            'user_id' => $this->regularUser->id,
            'order_item_id' => $this->orderItem->id,
            'rating' => 4,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->patchJson("/api/admin/reviews/{$review->id}/status", [
                'status' => 'approved',
            ]);

        $response->assertStatus(403);
    }
}
