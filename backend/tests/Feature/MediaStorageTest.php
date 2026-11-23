<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Role;
use App\Models\User;
use App\Services\MediaStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaStorageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_default_driver_is_local(): void
    {
        $service = new MediaStorageService;
        $this->assertEquals('local', $service->getDriver());
    }

    public function test_local_upload_stores_file_and_returns_standard_metadata(): void
    {
        $service = new MediaStorageService('local');
        $file = UploadedFile::fake()->image('test_product.jpg', 640, 480);

        $result = $service->upload($file, 'products');

        $this->assertEquals('local', $result['provider']);
        $this->assertEquals('public', $result['disk']);
        $this->assertNull($result['public_id']);
        $this->assertStringStartsWith('products/', $result['path']);
        $this->assertStringContainsString('/storage/products/', $result['url']);
        $this->assertStringStartsWith('/storage/products/', $result['relative_url']);
        $this->assertEquals('test_product.jpg', $result['name']);
        $this->assertGreaterThan(0, $result['size']);

        Storage::disk('public')->assertExists($result['path']);
    }

    public function test_local_delete_removes_file_safely(): void
    {
        $service = new MediaStorageService('local');
        $file = UploadedFile::fake()->image('delete_me.png', 100, 100);

        $result = $service->upload($file, 'temp');
        Storage::disk('public')->assertExists($result['path']);

        $deleted = $service->delete($result['path']);
        $this->assertTrue($deleted);
        Storage::disk('public')->assertMissing($result['path']);
    }

    public function test_delete_handles_full_storage_urls(): void
    {
        $service = new MediaStorageService('local');
        $file = UploadedFile::fake()->image('delete_url.png', 100, 100);

        $result = $service->upload($file, 'temp');
        Storage::disk('public')->assertExists($result['path']);

        // Delete using relative or absolute URL
        $deleted = $service->delete($result['relative_url']);
        $this->assertTrue($deleted);
        Storage::disk('public')->assertMissing($result['path']);
    }

    public function test_url_resolution_for_various_formats(): void
    {
        $service = new MediaStorageService('local');

        // External HTTPS
        $this->assertEquals(
            'https://example.com/sample.jpg',
            $service->url('https://example.com/sample.jpg')
        );

        // Local path
        $this->assertEquals(
            '/storage/products/test.webp',
            $service->url('products/test.webp')
        );

        // Already prefixed with /storage/
        $this->assertEquals(
            '/storage/products/test.webp',
            $service->url('/storage/products/test.webp')
        );
    }

    public function test_cloudinary_fallback_to_local_when_unconfigured(): void
    {
        Config::set('media.default', 'cloudinary');
        Config::set('media.fallback_to_local', true);
        Config::set('media.drivers.cloudinary.cloud_name', null);
        Config::set('media.drivers.cloudinary.api_key', null);
        Config::set('media.drivers.cloudinary.api_secret', null);
        Config::set('media.drivers.cloudinary.url', null);

        $service = new MediaStorageService('cloudinary');
        $file = UploadedFile::fake()->image('fallback_test.jpg', 200, 200);

        $result = $service->upload($file, 'products');

        $this->assertEquals('local', $result['provider']);
        Storage::disk('public')->assertExists($result['path']);
    }

    public function test_banner_model_url_accessors_support_both_local_and_cloudinary(): void
    {
        // Local banner
        $localBanner = Banner::create([
            'title' => 'Local Banner',
            'image_path' => 'banners/2026/11/test.jpg',
            'storage_provider' => 'local',
            'position' => 'home',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->assertEquals('/storage/banners/2026/11/test.jpg', $localBanner->image_url);

        // Cloudinary banner with full HTTPS URL
        $cloudBanner = Banner::create([
            'title' => 'Cloud Banner',
            'image_path' => 'https://res.cloudinary.com/demo/image/upload/sample.jpg',
            'storage_provider' => 'cloudinary',
            'public_id' => 'sample',
            'position' => 'home',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/sample.jpg', $cloudBanner->image_url);
    }

    public function test_product_image_model_url_accessors_support_both_local_and_cloudinary(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Test Phone',
            'slug' => 'test-phone-media-storage',
            'status' => 'active',
        ]);

        // Local product image
        $localImg = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'products/local_img.jpg',
            'storage_provider' => 'local',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $this->assertEquals('/storage/products/local_img.jpg', $localImg->image_url);

        // Cloudinary product image with full URL
        $cloudImg = ProductImage::create([
            'product_id' => $product->id,
            'image_path' => 'https://res.cloudinary.com/demo/image/upload/product123.jpg',
            'storage_provider' => 'cloudinary',
            'public_id' => 'mobiex/products/product123',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $this->assertEquals('https://res.cloudinary.com/demo/image/upload/product123.jpg', $cloudImg->image_url);
    }

    public function test_admin_banner_upload_api(): void
    {
        $admin = $this->createAdminUser('banners.manage');
        $file = UploadedFile::fake()->image('banner_hero.webp', 1920, 600);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/admin/banners/upload-image', [
                'image' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
                'message' => 'Tải ảnh banner lên thành công.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'path',
                    'url',
                    'provider',
                    'name',
                    'size',
                ],
            ]);
    }

    protected function createAdminUser(string $permissionName): User
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(
            ['slug' => 'test-admin'],
            ['name' => 'Test Admin', 'description' => 'Test Admin Role']
        );

        $perm = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['slug' => $permissionName, 'description' => $permissionName, 'module' => 'admin']
        );

        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);

        return $user;
    }
}
