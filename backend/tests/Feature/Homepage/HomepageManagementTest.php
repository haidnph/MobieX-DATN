<?php

namespace Tests\Feature\Homepage;

use App\Models\Banner;
use App\Models\HomepageSection;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\HomepageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomepageManagementTest extends TestCase
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

    public function test_public_can_get_published_homepage_storefront_data(): void
    {
        // Setup banner
        Banner::create([
            'title' => 'iPhone 16 Pro Max',
            'image_path' => 'https://example.com/banner.jpg',
            'position' => 'home',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/homepage');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'updated_at',
                    'sections',
                ],
            ]);

        $sections = $response->json('data.sections');
        $this->assertNotEmpty($sections);

        // Verify default sections exist
        $sectionKeys = collect($sections)->pluck('section_key')->toArray();
        $this->assertContains('hero_banner', $sectionKeys);
        $this->assertContains('featured_products', $sectionKeys);
    }

    public function test_regular_user_cannot_access_admin_homepage(): void
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')
            ->getJson('/api/admin/homepage');

        $response->assertStatus(403);
    }

    public function test_admin_can_get_and_update_sections(): void
    {
        // 1. Get sections
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/homepage');

        $res->assertStatus(200)
            ->assertJsonPath('status', true);

        $sections = $res->json('data');
        $this->assertNotEmpty($sections);

        $firstSection = $sections[0];
        $sectionId = $firstSection['id'];

        // 2. Update section in draft
        $updateRes = $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/admin/homepage/sections/{$sectionId}", [
                'title' => 'Tiêu đề cập nhật thử nghiệm',
                'subtitle' => 'Mô tả mới',
                'layout_type' => 'carousel',
                'items_limit' => 10,
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.title', 'Tiêu đề cập nhật thử nghiệm')
            ->assertJsonPath('data.layout_type', 'carousel');

        $this->assertEquals('Tiêu đề cập nhật thử nghiệm', HomepageSection::find($sectionId)->title);
    }

    public function test_admin_can_reorder_sections(): void
    {
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/homepage');
        $sections = $res->json('data');

        $id1 = $sections[0]['id'];
        $id2 = $sections[1]['id'];

        $reorderRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/admin/homepage/reorder', [
                'orders' => [
                    ['id' => $id1, 'sort_order' => 10, 'is_active' => true],
                    ['id' => $id2, 'sort_order' => 5, 'is_active' => true],
                ],
            ]);

        $reorderRes->assertStatus(200);

        $this->assertEquals(10, HomepageSection::find($id1)->sort_order);
        $this->assertEquals(5, HomepageSection::find($id2)->sort_order);
    }

    public function test_admin_can_publish_and_it_syncs_to_storefront(): void
    {
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/homepage');
        $sections = $res->json('data');

        $target = collect($sections)->firstWhere('section_key', 'featured_products');
        $targetId = $target['id'];

        // Update draft title
        $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/admin/homepage/sections/{$targetId}", [
                'title' => 'Sản phẩm mới xuất bản 2026',
                'layout_type' => 'grid',
                'items_limit' => 8,
                'sort_order' => 2,
                'is_active' => true,
            ]);

        // Prior to publish, public storefront does not have draft title
        Cache::forget(HomepageService::CACHE_KEY);
        $publicBefore = $this->getJson('/api/homepage');
        $foundBefore = collect($publicBefore->json('data.sections'))->firstWhere('section_key', 'featured_products');
        $this->assertNotEquals('Sản phẩm mới xuất bản 2026', $foundBefore['title']);

        // Publish
        $pubRes = $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/admin/homepage/publish');

        $pubRes->assertStatus(200)
            ->assertJsonPath('status', true);

        // After publish, public storefront immediately reflects the change
        $publicAfter = $this->getJson('/api/homepage');
        $foundAfter = collect($publicAfter->json('data.sections'))->firstWhere('section_key', 'featured_products');
        $this->assertEquals('Sản phẩm mới xuất bản 2026', $foundAfter['title']);
    }

    public function test_admin_can_preview_draft_before_publishing(): void
    {
        $res = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/homepage');
        $sections = $res->json('data');

        $target = collect($sections)->firstWhere('section_key', 'hero_banner');
        $targetId = $target['id'];

        $this->actingAs($this->adminUser, 'sanctum')
            ->putJson("/api/admin/homepage/sections/{$targetId}", [
                'title' => 'Draft Preview Hero Banner',
                'layout_type' => 'carousel',
                'items_limit' => 3,
                'sort_order' => 1,
                'is_active' => true,
            ]);

        $previewRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/homepage/preview');

        $previewRes->assertStatus(200)
            ->assertJsonPath('data.mode', 'draft_preview');

        $previewHero = collect($previewRes->json('data.sections'))->firstWhere('section_key', 'hero_banner');
        $this->assertEquals('Draft Preview Hero Banner', $previewHero['title']);
    }

    public function test_admin_can_revert_and_reset_defaults(): void
    {
        $this->actingAs($this->adminUser, 'sanctum')
            ->postJson('/api/admin/homepage/reset-defaults')
            ->assertStatus(200);

        $auditLogsRes = $this->actingAs($this->adminUser, 'sanctum')
            ->getJson('/api/admin/homepage/audit-logs');

        $auditLogsRes->assertStatus(200)
            ->assertJsonPath('status', true);
        $this->assertNotEmpty($auditLogsRes->json('data'));
    }
}
