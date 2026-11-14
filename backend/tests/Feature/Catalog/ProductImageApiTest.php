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

class ProductImageApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::factory()->create([
            'is_active' => true,
        ]);

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
    }

    private function makeImage(string $name = 'photo.jpg', int $kilobytes = 100): UploadedFile
    {
        return UploadedFile::fake()->image($name, 100, 100)->size($kilobytes);
    }

    // IMG-01: Upload 1 ảnh hợp lệ
    public function test_img01_upload_single_valid_image(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'images' => [$this->makeImage()],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);
        $this->assertDatabaseCount('product_images', 1);
    }

    // IMG-02: Upload 10 ảnh cùng lúc
    public function test_img02_upload_ten_images_at_once(): void
    {
        Sanctum::actingAs($this->admin);

        $images = [];
        for ($i = 0; $i < 10; $i++) {
            $images[] = $this->makeImage("phone_{$i}.jpg");
        }

        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'images' => $images,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseCount('product_images', 10);
    }

    // IMG-03: Upload file vượt 5MB (tối đa 5120KB)
    public function test_img03_reject_file_exceeding_5mb(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'images' => [$this->makeImage('big.jpg', 6000)],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    // IMG-04: Upload file sai định dạng (PDF)
    public function test_img04_reject_wrong_format(): void
    {
        Sanctum::actingAs($this->admin);

        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'images' => [$pdf],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['images.0']);
    }

    // IMG-05: Upload file PHP giả đuôi JPG
    public function test_img05_reject_disguised_php_file(): void
    {
        Sanctum::actingAs($this->admin);

        $fakePhp = UploadedFile::fake()->create('shell.jpg', 10, 'text/plain');
        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'images' => [$fakePhp],
        ]);

        $response->assertStatus(422);
    }

    // IMG-06: Upload nhiều ảnh có một file lỗi (batch validation từ chối cả batch)
    public function test_img06_mixed_valid_invalid_files(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'images' => [
                $this->makeImage(),
                UploadedFile::fake()->create('bad.pdf', 50, 'application/pdf'),
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('product_images', 0);
    }

    // IMG-07: Chọn ảnh đại diện
    public function test_img07_set_primary_image(): void
    {
        Sanctum::actingAs($this->admin);

        $image = ProductImage::create([
            'product_id' => $this->product->id,
            'image_path' => 'products/iphone.jpg',
            'is_primary' => false,
            'sort_order' => 0,
        ]);

        $response = $this->patchJson("/api/product-images/{$image->id}/primary");

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
        $this->assertTrue((bool) $image->fresh()->is_primary);
    }

    // IMG-08: Chọn ảnh khác làm đại diện (ảnh cũ tự bỏ trạng thái đại diện)
    public function test_img08_switching_primary_clears_old_primary(): void
    {
        Sanctum::actingAs($this->admin);

        $imageA = ProductImage::create([
            'product_id' => $this->product->id,
            'image_path' => 'products/iphone_a.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $imageB = ProductImage::create([
            'product_id' => $this->product->id,
            'image_path' => 'products/iphone_b.jpg',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $response = $this->patchJson("/api/product-images/{$imageB->id}/primary");

        $response->assertStatus(200);
        $this->assertFalse((bool) $imageA->fresh()->is_primary);
        $this->assertTrue((bool) $imageB->fresh()->is_primary);
    }

    // IMG-09: Kéo thả đổi thứ tự - lưu đúng sort_order vào DB
    public function test_img09_reorder_persists_to_database(): void
    {
        Sanctum::actingAs($this->admin);

        $img1 = ProductImage::create([
            'product_id' => $this->product->id,
            'image_path' => 'products/p1.jpg',
            'sort_order' => 0,
        ]);
        $img2 = ProductImage::create([
            'product_id' => $this->product->id,
            'image_path' => 'products/p2.jpg',
            'sort_order' => 1,
        ]);

        $response = $this->patchJson('/api/product-images/reorder', [
            'images' => [
                ['id' => $img1->id, 'sort_order' => 1],
                ['id' => $img2->id, 'sort_order' => 0],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', true);
        $this->assertEquals(1, $img1->fresh()->sort_order);
        $this->assertEquals(0, $img2->fresh()->sort_order);
    }

    // IMG-10: Xóa ảnh phụ thành công
    public function test_img10_delete_non_primary_image(): void
    {
        Sanctum::actingAs($this->admin);

        $image = ProductImage::create([
            'product_id' => $this->product->id,
            'image_path' => 'products/sub.jpg',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $response = $this->deleteJson("/api/product-images/{$image->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    // IMG-11: Xóa ảnh đại diện an toàn
    public function test_img11_delete_primary_image(): void
    {
        Sanctum::actingAs($this->admin);

        $image = ProductImage::create([
            'product_id' => $this->product->id,
            'image_path' => 'products/cover.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $response = $this->deleteJson("/api/product-images/{$image->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('product_images', ['id' => $image->id]);
    }

    public function test_img11_delete_primary_image_promotes_next_image_to_primary(): void
    {
        Sanctum::actingAs($this->admin);

        $img1 = ProductImage::create([
            'product_id' => $this->product->id,
            'image_path' => 'products/cover1.jpg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $img2 = ProductImage::create([
            'product_id' => $this->product->id,
            'image_path' => 'products/cover2.jpg',
            'is_primary' => false,
            'sort_order' => 1,
        ]);

        $this->deleteJson("/api/product-images/{$img1->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('product_images', ['id' => $img1->id]);
        $this->assertTrue((bool) $img2->fresh()->is_primary);
    }

    // IMG-12: Upload ảnh cho biến thể hợp lệ
    public function test_img12_upload_image_for_valid_variant(): void
    {
        Sanctum::actingAs($this->admin);

        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'IPHONE-TITAN-256',
            'option_signature' => 'color:titan,storage:256gb',
            'price' => 30000000,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'variant_id' => $variant->id,
            'images' => [$this->makeImage()],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('product_images', [
            'product_id' => $this->product->id,
            'product_variant_id' => $variant->id,
        ]);
    }

    // IMG-13: Upload ảnh vào variant thuộc sản phẩm khác bị từ chối (422)
    public function test_img13_reject_variant_from_different_product(): void
    {
        Sanctum::actingAs($this->admin);

        $otherProduct = Product::create([
            'category_id' => $this->product->category_id,
            'brand_id' => $this->product->brand_id,
            'product_type' => 'phone',
            'name' => 'Galaxy S24',
            'slug' => 'galaxy-s24',
            'status' => 'active',
        ]);

        $otherVariant = ProductVariant::create([
            'product_id' => $otherProduct->id,
            'sku' => 'GALAXY-BLACK',
            'option_signature' => 'color:black',
            'price' => 20000000,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'variant_id' => $otherVariant->id,
            'images' => [$this->makeImage()],
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false);
    }

    // IMG-14: Biến thể không có ảnh riêng trả mảng rỗng (không lỗi 500)
    public function test_img14_variant_without_images_returns_empty(): void
    {
        $variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'IPHONE-BLUE',
            'option_signature' => 'color:blue',
            'price' => 30000000,
            'is_active' => true,
        ]);

        $response = $this->getJson(
            "/api/product-images?product_id={$this->product->id}&product_variant_id={$variant->id}"
        );

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(0, 'data');
    }

    // IMG-15: Guest chưa xác thực không thể upload ảnh (401)
    public function test_img15_guest_cannot_upload_images(): void
    {
        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'images' => [$this->makeImage()],
        ]);

        $response->assertStatus(401);
    }

    // IMG-16: File vật lý bị xóa khi bản ghi image bị xóa
    public function test_img16_physical_file_deleted_on_record_delete(): void
    {
        Sanctum::actingAs($this->admin);

        $file = $this->makeImage('todelete.jpg');
        $uploadResponse = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'images' => [$file],
        ]);

        $uploadResponse->assertStatus(201);
        $imageId = $uploadResponse->json('data.0.id');

        $this->deleteJson("/api/product-images/{$imageId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('product_images', ['id' => $imageId]);
    }

    // IMG-17: Thiếu tham số bắt buộc không để lại bản ghi rác
    public function test_img17_failed_upload_no_orphan_records(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/product-images', [
            'images' => [$this->makeImage()],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('product_images', 0);
    }

    // IMG-18: Sản phẩm chưa có ảnh trả về mảng rỗng không lỗi
    public function test_img18_product_without_images_returns_empty(): void
    {
        $response = $this->getJson("/api/product-images?product_id={$this->product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(0, 'data');
    }

    // IMG-19: Upload nhiều ảnh với primary_index được chỉ định
    public function test_img19_upload_multiple_images_with_primary_index(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'primary_index' => 1,
            'images' => [
                $this->makeImage('img0.jpg'),
                $this->makeImage('img1.jpg'),
                $this->makeImage('img2.jpg'),
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $images = ProductImage::where('product_id', $this->product->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(3, $images);
        $this->assertFalse((bool) $images[0]->is_primary);
        $this->assertTrue((bool) $images[1]->is_primary);
        $this->assertFalse((bool) $images[2]->is_primary);
    }

    // IMG-20: Tự động gán ảnh đầu tiên làm ảnh chính khi sản phẩm chưa có ảnh
    public function test_img20_auto_primary_when_no_existing_images(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/product-images', [
            'product_id' => $this->product->id,
            'images' => [
                $this->makeImage('photo1.jpg'),
                $this->makeImage('photo2.jpg'),
            ],
        ]);

        $response->assertStatus(201);
        $images = ProductImage::where('product_id', $this->product->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $images);
        $this->assertTrue((bool) $images[0]->is_primary);
        $this->assertFalse((bool) $images[1]->is_primary);
    }

    // IMG-21: Tạo sản phẩm mới kèm danh sách ảnh multipart
    public function test_img21_create_product_with_multipart_images(): void
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/products', [
            'name' => 'Xiaomi 14 Ultra',
            'slug' => 'xiaomi-14-ultra-test',
            'brand_id' => $this->product->brand_id,
            'category_id' => $this->product->category_id,
            'price' => 24000000,
            'stock_quantity' => 20,
            'primary_index' => 1,
            'images' => [
                $this->makeImage('mi14_front.jpg'),
                $this->makeImage('mi14_back.jpg'),
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true);

        $newProductId = $response->json('data.id');
        $images = ProductImage::where('product_id', $newProductId)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $images);
        $this->assertFalse((bool) $images[0]->is_primary);
        $this->assertTrue((bool) $images[1]->is_primary);
    }
}
