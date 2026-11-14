<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductGalleryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Brand $brand;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::factory()->create([
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Điện thoại',
            'slug' => 'dien-thoai',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name' => 'Samsung',
            'slug' => 'samsung',
            'is_active' => true,
        ]);
    }

    private function makeImage(string $name = 'photo.jpg', int $kilobytes = 150): UploadedFile
    {
        return UploadedFile::fake()->image($name, 600, 600)->size($kilobytes);
    }

    /**
     * Test 1: Tạo sản phẩm mới với 5 ảnh, chỉ định ảnh thứ 2 (index 1) làm ảnh đại diện, kiểm tra DB & Storage.
     */
    public function test_scenario1_create_product_with_five_images_custom_primary(): void
    {
        Sanctum::actingAs($this->admin);

        $images = [
            $this->makeImage('front.jpg'),
            $this->makeImage('back.jpg'),
            $this->makeImage('side_left.jpg'),
            $this->makeImage('side_right.jpg'),
            $this->makeImage('camera_zoom.jpg'),
        ];

        $response = $this->postJson('/api/products', [
            'name' => 'Samsung Galaxy S24 Ultra Test',
            'slug' => 'samsung-galaxy-s24-ultra-test',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price' => 31990000,
            'compare_price' => 33990000,
            'stock_quantity' => 40,
            'primary_index' => 1, // ảnh back.jpg là ảnh chính
            'images' => $images,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $productId = $response->json('data.id');
        $this->assertNotNull($productId);

        // Kiểm tra database có đúng 5 ảnh
        $dbImages = ProductImage::where('product_id', $productId)->orderBy('sort_order')->get();
        $this->assertCount(5, $dbImages);

        // Kiểm tra đúng ảnh thứ 2 (index 1) là ảnh chính
        $this->assertFalse((bool) $dbImages[0]->is_primary);
        $this->assertTrue((bool) $dbImages[1]->is_primary);
        $this->assertFalse((bool) $dbImages[2]->is_primary);
        $this->assertFalse((bool) $dbImages[3]->is_primary);
        $this->assertFalse((bool) $dbImages[4]->is_primary);

        // Kiểm tra sort_order tuần tự 0..4
        for ($i = 0; $i < 5; $i++) {
            $this->assertEquals($i, $dbImages[$i]->sort_order);
        }

        // Kiểm tra Product thumbnail tự động trỏ vào ảnh chính
        $product = Product::with('images')->find($productId);
        $this->assertEquals($dbImages[1]->image_url, $product->thumbnail);
    }

    /**
     * Test 2: Chỉnh sửa sản phẩm: Thêm 2 ảnh mới, xóa 1 ảnh cũ, đổi ảnh đại diện, đổi thứ tự gallery.
     */
    public function test_scenario2_edit_product_add_two_delete_one_change_primary_and_reorder(): void
    {
        Sanctum::actingAs($this->admin);

        // Khởi tạo sản phẩm có sẵn 5 ảnh
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'product_type' => 'phone',
            'name' => 'Galaxy Test Model',
            'slug' => 'galaxy-test-model',
            'status' => 'active',
        ]);

        $initialImages = [];
        for ($i = 0; $i < 5; $i++) {
            $file = $this->makeImage("initial_{$i}.jpg");
            $path = $file->store('products', 'public');
            $initialImages[] = ProductImage::create([
                'product_id' => $product->id,
                'image_url' => asset('storage/' . $path),
                'is_primary' => ($i === 0),
                'sort_order' => $i,
            ]);
        }
        $this->assertDatabaseCount('product_images', 5);

        // 1. Thêm 2 ảnh mới qua API
        $newFiles = [$this->makeImage('new_angle_1.jpg'), $this->makeImage('new_angle_2.jpg')];
        $uploadRes = $this->postJson('/api/product-images', [
            'product_id' => $product->id,
            'images' => $newFiles,
        ]);
        $uploadRes->assertStatus(201);
        $this->assertDatabaseCount('product_images', 7);

        // 2. Xóa 1 ảnh cũ (ảnh index 2)
        $imageToDelete = $initialImages[2];
        $deleteRes = $this->deleteJson("/api/product-images/{$imageToDelete->id}");
        $deleteRes->assertStatus(200);
        $this->assertDatabaseMissing('product_images', ['id' => $imageToDelete->id]);
        $this->assertDatabaseCount('product_images', 6);

        // 3. Đổi ảnh đại diện sang ảnh index 3
        $newPrimaryTarget = $initialImages[3];
        $setPrimaryRes = $this->patchJson("/api/product-images/{$newPrimaryTarget->id}/primary");
        $setPrimaryRes->assertStatus(200);

        $this->assertTrue((bool) $newPrimaryTarget->fresh()->is_primary);
        $this->assertFalse((bool) $initialImages[0]->fresh()->is_primary);

        // 4. Đổi thứ tự gallery
        $currentImages = ProductImage::where('product_id', $product->id)->orderBy('sort_order')->get();
        $reorderedPayload = [];
        $reverseOrder = array_reverse($currentImages->pluck('id')->toArray());
        foreach ($reverseOrder as $newOrder => $imgId) {
            $reorderedPayload[] = ['id' => $imgId, 'sort_order' => $newOrder];
        }

        $reorderRes = $this->patchJson('/api/product-images/reorder', [
            'images' => $reorderedPayload,
        ]);
        $reorderRes->assertStatus(200);

        // Tải lại kiểm tra tính toàn vẹn
        $freshList = ProductImage::where('product_id', $product->id)->orderBy('sort_order')->get();
        $this->assertCount(6, $freshList);
        $this->assertEquals($reverseOrder[0], $freshList[0]->id);
        $this->assertEquals(0, $freshList[0]->sort_order);
    }

    /**
     * Test 3: Xử lý lỗi: từ chối file không hợp lệ, dung lượng quá tải, không để lại file rác hay bản ghi mồ côi.
     */
    public function test_scenario3_error_handling_and_no_orphan_records(): void
    {
        Sanctum::actingAs($this->admin);

        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'product_type' => 'phone',
            'name' => 'Error Test Product',
            'slug' => 'error-test-product',
            'status' => 'active',
        ]);

        // File text giả danh ảnh
        $badFile = UploadedFile::fake()->create('malicious.php', 10, 'text/plain');
        $res1 = $this->postJson('/api/product-images', [
            'product_id' => $product->id,
            'images' => [$badFile],
        ]);
        $res1->assertStatus(422);
        $this->assertDatabaseCount('product_images', 0);

        // File quá cỡ 5MB
        $hugeFile = UploadedFile::fake()->image('huge.jpg')->size(6000);
        $res2 = $this->postJson('/api/product-images', [
            'product_id' => $product->id,
            'images' => [$hugeFile],
        ]);
        $res2->assertStatus(422);
        $this->assertDatabaseCount('product_images', 0);

        // File hợp lệ kèm file lỗi: cả batch bị từ chối nguyên vẹn
        $res3 = $this->postJson('/api/product-images', [
            'product_id' => $product->id,
            'images' => [
                $this->makeImage('good.jpg'),
                UploadedFile::fake()->create('bad.exe', 100, 'application/octet-stream'),
            ],
        ]);
        $res3->assertStatus(422);
        $this->assertDatabaseCount('product_images', 0);
    }

    /**
     * Test 4: Regression: Thêm & cập nhật sản phẩm giữ nguyên giá, tồn kho, biến thể và so sánh.
     */
    public function test_scenario4_regression_preserves_variants_pricing_and_inventory(): void
    {
        Sanctum::actingAs($this->admin);

        // Tạo sản phẩm qua API
        $res = $this->postJson('/api/products', [
            'name' => 'Galaxy S25 Plus',
            'slug' => 'galaxy-s25-plus',
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'price' => 26990000,
            'compare_price' => 28990000,
            'stock_quantity' => 15,
            'sku' => 'S25P-256G-BLK',
        ]);

        $res->assertStatus(201);
        $prodId = $res->json('data.id');

        $product = Product::with(['variants.inventory', 'images'])->find($prodId);
        $this->assertNotNull($product);
        $this->assertCount(1, $product->variants);

        $variant = $product->variants->first();
        $this->assertEquals(26990000, (float) $variant->price);
        $this->assertEquals(28990000, (float) $variant->compare_price);
        $this->assertEquals(15, (int) $variant->inventory->quantity);

        // Cập nhật giá và tồn kho
        $updateRes = $this->putJson("/api/products/{$prodId}", [
            'price' => 25990000,
            'stock_quantity' => 20,
        ]);
        $updateRes->assertStatus(200);

        $variant->refresh();
        $this->assertEquals(25990000, (float) $variant->price);
        $this->assertEquals(20, (int) $variant->inventory->fresh()->quantity);

        // Đảm bảo so sánh sản phẩm hoạt động trơn tru
        $compareRes = $this->getJson("/api/products/compare?ids={$prodId}");
        $compareRes->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(1, 'data.products');
    }
}
