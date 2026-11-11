<?php

namespace Tests\Feature\Admin;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCouponCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $viewPerm = Permission::firstOrCreate(
            ['slug' => 'coupons.view'],
            ['name' => 'Xem coupon', 'description' => 'Xem coupon']
        );

        $managePerm = Permission::firstOrCreate(
            ['slug' => 'coupons.manage'],
            ['name' => 'Quản lý coupon', 'description' => 'Quản lý coupon']
        );

        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Quản trị viên', 'description' => 'Admin role', 'is_system' => true]
        );
        $adminRole->permissions()->syncWithoutDetaching([$viewPerm->id, $managePerm->id]);

        $this->adminUser = User::factory()->create(['is_active' => true]);
        $this->adminUser->roles()->attach($adminRole->id);

        $this->regularUser = User::factory()->create(['is_active' => true]);
    }

    public function test_regular_user_cannot_access_admin_coupons(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/admin/coupons');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_coupon(): void
    {
        $payload = [
            'code' => 'SUMMER2026',
            'discount_type' => 'percent',
            'discount_value' => 15,
            'min_order_amount' => 1000000,
            'max_discount' => 500000,
            'usage_limit' => 100,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/admin/coupons', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Tạo mã giảm giá thành công.',
                'data' => [
                    'code' => 'SUMMER2026',
                    'discount_type' => 'percent',
                ],
            ]);

        $this->assertDatabaseHas('coupons', [
            'code' => 'SUMMER2026',
            'discount_type' => 'percent',
        ]);
    }

    public function test_admin_can_list_and_filter_coupons(): void
    {
        Coupon::create([
            'code' => 'WINTER50',
            'discount_type' => 'percent',
            'discount_value' => 50,
            'is_active' => true,
        ]);

        Coupon::create([
            'code' => 'SPRING20',
            'discount_type' => 'fixed',
            'discount_value' => 20000,
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/coupons?search=WINTER');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('WINTER50', $data[0]['code']);
    }

    public function test_admin_can_view_coupon_detail(): void
    {
        $coupon = Coupon::create([
            'code' => 'DETAILTEST',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/admin/coupons/{$coupon->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $coupon->id,
                    'code' => 'DETAILTEST',
                ],
            ]);
    }

    public function test_admin_can_update_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'OLDCODE',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/admin/coupons/{$coupon->id}", [
                'discount_value' => 70000,
                'is_active' => false,
            ]);

        $response->assertStatus(200);
        $this->assertEquals(70000, $coupon->fresh()->discount_value);
        $this->assertFalse($coupon->fresh()->is_active);
    }

    public function test_admin_can_delete_unused_coupon(): void
    {
        $coupon = Coupon::create([
            'code' => 'DELETEME',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/coupons/{$coupon->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('coupons', ['id' => $coupon->id]);
    }

    public function test_admin_cannot_delete_used_coupon(): void
    {
        $shippingMethod = ShippingMethod::create([
            'name' => 'Tiêu chuẩn',
            'code' => 'STANDARD',
            'fee' => 30000,
            'is_active' => true,
        ]);

        $coupon = Coupon::create([
            'code' => 'USEDCOUPON',
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'is_active' => true,
            'used_count' => 1,
        ]);

        Order::create([
            'user_id' => $this->regularUser->id,
            'order_code' => 'MX-ORD-TEST',
            'coupon_id' => $coupon->id,
            'coupon_code' => $coupon->code,
            'shipping_method_id' => $shippingMethod->id,
            'shipping_method_code' => $shippingMethod->code,
            'shipping_method_name' => $shippingMethod->name,
            'subtotal' => 1000000,
            'discount_amount' => 50000,
            'shipping_fee' => 30000,
            'total_amount' => 980000,
            'shipping_name' => 'Test',
            'shipping_phone' => '0987654321',
            'shipping_province_code' => 'VN-HN',
            'shipping_province_name' => 'Ha Noi',
            'shipping_ward_code' => '001',
            'shipping_ward_name' => 'Dich Vong',
            'shipping_address_line' => '123 Cau Giay',
            'payment_method' => 'cod',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/coupons/{$coupon->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('coupons', ['id' => $coupon->id]);
    }
}
