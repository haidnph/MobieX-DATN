<?php

namespace Tests\Feature\Banners;

use App\Models\Banner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            'status' => 'inactive',
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

    public function test_admin_can_crud_banners_with_extended_fields(): void
    {
        // Create
        $createRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/admin/banners', [
                'title' => 'iPhone Mega Sale',
                'name' => 'Chiến dịch Tết 2026',
                'description' => 'Giảm tới 5 triệu đồng cho các mẫu iPhone 16',
                'image_path' => '/storage/banners/iphone-desktop.webp',
                'mobile_image_path' => '/storage/banners/iphone-mobile.webp',
                'alt_text' => 'Banner iPhone 16 Pro Max',
                'cta_label' => 'Mua ngay',
                'target_url' => '/products',
                'position' => 'home',
                'sort_order' => 5,
                'status' => 'published',
                'is_active' => true,
            ]);

        $createRes->assertStatus(201);
        $bannerId = $createRes->json('data.id');

        $this->assertDatabaseHas('banners', [
            'id' => $bannerId,
            'title' => 'iPhone Mega Sale',
            'name' => 'Chiến dịch Tết 2026',
            'cta_label' => 'Mua ngay',
        ]);

        // Show
        $showRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson("/api/admin/banners/{$bannerId}");
        $showRes->assertStatus(200)
            ->assertJsonPath('data.title', 'iPhone Mega Sale')
            ->assertJsonPath('data.name', 'Chiến dịch Tết 2026')
            ->assertJsonPath('data.cta_label', 'Mua ngay');

        // Update
        $updateRes = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/admin/banners/{$bannerId}", [
                'title' => 'iPhone Super Sale Updated',
                'sort_order' => 1,
                'cta_label' => 'Xem chi tiết',
            ]);
        $updateRes->assertStatus(200);
        $this->assertEquals('iPhone Super Sale Updated', Banner::find($bannerId)->title);
        $this->assertEquals('Xem chi tiết', Banner::find($bannerId)->cta_label);

        // Delete
        $deleteRes = $this->actingAs($this->adminUser, 'sanctum')
            ->deleteJson("/api/admin/banners/{$bannerId}");
        $deleteRes->assertStatus(200);
        $this->assertSoftDeleted('banners', ['id' => $bannerId]);
    }

    public function test_admin_can_upload_banner_image(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('hero-banner.jpg', 1920, 600)->size(1500);

        $response = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/admin/banners/upload-image', [
                'image' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'path',
                    'url',
                    'name',
                    'size',
                ],
            ]);

        $storedPath = $response->json('data.path');
        Storage::disk('public')->assertExists($storedPath);
    }
}
