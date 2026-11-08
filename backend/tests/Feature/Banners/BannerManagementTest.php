<?php

namespace Tests\Feature\Banners;

use App\Models\Banner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;

    private User $regularUser;

    protected function setUp(): void
    {
        parent::setUp();

        $viewPerm = Permission::firstOrCreate(
            ['slug' => 'banners.view'],
            ['name' => 'Xem banner', 'description' => 'Xem banner']
        );

        $managePerm = Permission::firstOrCreate(
            ['slug' => 'banners.manage'],
            ['name' => 'Quản lý banner', 'description' => 'Quản lý banner']
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

    public function test_public_can_get_only_active_banners_ordered(): void
    {
        // 1. Active banner order 2
        Banner::create([
            'title' => 'Banner 2',
            'image_path' => '/img/banner2.jpg',
            'position' => 'home',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        // 2. Active banner order 1
        Banner::create([
            'title' => 'Banner 1',
            'image_path' => '/img/banner1.jpg',
            'position' => 'home',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        // 3. Inactive banner
        Banner::create([
            'title' => 'Inactive Banner',
            'image_path' => '/img/inactive.jpg',
            'position' => 'home',
            'sort_order' => 0,
            'is_active' => false,
        ]);

        // 4. Expired banner
        Banner::create([
            'title' => 'Expired Banner',
            'image_path' => '/img/expired.jpg',
            'position' => 'home',
            'sort_order' => 0,
            'end_at' => now()->subDay(),
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/banners');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
        $this->assertEquals('Banner 1', $data[0]['title']);
        $this->assertEquals('Banner 2', $data[1]['title']);
    }

    public function test_regular_user_cannot_access_admin_banners(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/admin/banners');

        $response->assertStatus(403);
    }

    public function test_admin_can_crud_banners(): void
    {
        // Create
        $createRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/admin/banners', [
                'title' => 'iPhone Mega Sale',
                'image_path' => '/uploads/banners/iphone.jpg',
                'target_url' => '/products/iphone-15',
                'position' => 'home',
                'sort_order' => 5,
                'is_active' => true,
            ]);

        $createRes->assertStatus(201);
        $bannerId = $createRes->json('data.id');

        $this->assertDatabaseHas('banners', [
            'id' => $bannerId,
            'title' => 'iPhone Mega Sale',
        ]);

        // Show
        $showRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/admin/banners/{$bannerId}");
        $showRes->assertStatus(200)
            ->assertJsonPath('data.title', 'iPhone Mega Sale');

        // Update
        $updateRes = $this->actingAs($this->adminUser, 'sanctum')
            ->patchJson("/api/admin/banners/{$bannerId}", [
                'title' => 'iPhone Super Sale',
                'sort_order' => 1,
            ]);
        $updateRes->assertStatus(200);
        $this->assertEquals('iPhone Super Sale', Banner::find($bannerId)->title);

        // Delete
        $deleteRes = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/banners/{$bannerId}");
        $deleteRes->assertStatus(200);
        $this->assertDatabaseMissing('banners', ['id' => $bannerId]);
    }
}
