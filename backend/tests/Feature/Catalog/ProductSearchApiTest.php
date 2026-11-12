<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchApiTest extends TestCase
{
    use RefreshDatabase;

    private Product $phone1;
    private Product $phone2;
    private Brand $brand1;
    private Brand $brand2;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create([
            'name' => 'Điện thoại',
            'slug' => 'dien-thoai',
            'status' => true,
        ]);

        $this->brand1 = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'status' => true,
        ]);

        $this->brand2 = Brand::create([
            'name' => 'Samsung',
            'slug' => 'samsung',
            'status' => true,
        ]);

        $this->phone1 = Product::create([
            'name' => 'iPhone 16 Pro Max',
            'slug' => 'iphone-16-pro-max',
            'category_id' => $category->id,
            'brand_id' => $this->brand1->id,
            'product_type' => 'phone',
            'status' => 'active',
            'base_price' => 34990000,
        ]);

        ProductVariant::create([
            'product_id' => $this->phone1->id,
            'sku' => 'IP16PM-TEST',
            'price' => 34990000,
            'is_active' => true,
        ]);

        $this->phone2 = Product::create([
            'name' => 'Samsung Galaxy S24 Ultra',
            'slug' => 'samsung-galaxy-s24-ultra',
            'category_id' => $category->id,
            'brand_id' => $this->brand2->id,
            'product_type' => 'phone',
            'status' => 'active',
            'base_price' => 29990000,
        ]);

        ProductVariant::create([
            'product_id' => $this->phone2->id,
            'sku' => 'S24U-TEST',
            'price' => 29990000,
            'is_active' => true,
        ]);
    }

    public function test_can_search_products_by_keyword(): void
    {
        $response = $this->getJson('/api/products?keyword=iPhone');

        $response->assertStatus(200);
        $data = $response->json('data.data') ?? $response->json('data') ?? $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals($this->phone1->id, $data[0]['id']);
    }

    public function test_can_search_products_using_q_alias(): void
    {
        $response = $this->getJson('/api/products?q=Samsung');

        $response->assertStatus(200);
        $data = $response->json('data.data') ?? $response->json('data') ?? $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals($this->phone2->id, $data[0]['id']);
    }

    public function test_can_search_products_using_search_alias(): void
    {
        $response = $this->getJson('/api/products?search=Ultra');

        $response->assertStatus(200);
        $data = $response->json('data.data') ?? $response->json('data') ?? $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals($this->phone2->id, $data[0]['id']);
    }

    public function test_can_filter_by_multiple_brands_comma_separated(): void
    {
        $response = $this->getJson('/api/products?brand_id=' . $this->brand1->id . ',' . $this->brand2->id);

        $response->assertStatus(200);
        $data = $response->json('data.data') ?? $response->json('data') ?? $response->json();
        $this->assertCount(2, $data);
    }

    public function test_can_sort_products_by_price_asc_and_desc(): void
    {
        // Price asc: phone2 (29.99M) then phone1 (34.99M)
        $ascResponse = $this->getJson('/api/products?sort_by=price_asc');
        $ascResponse->assertStatus(200);
        $ascData = $ascResponse->json('data.data');
        $this->assertEquals($this->phone2->id, $ascData[0]['id']);
        $this->assertEquals($this->phone1->id, $ascData[1]['id']);

        // Price desc: phone1 (34.99M) then phone2 (29.99M)
        $descResponse = $this->getJson('/api/products?sort_by=price_desc');
        $descResponse->assertStatus(200);
        $descData = $descResponse->json('data.data');
        $this->assertEquals($this->phone1->id, $descData[0]['id']);
        $this->assertEquals($this->phone2->id, $descData[1]['id']);
    }

    public function test_can_sort_products_by_name(): void
    {
        $nameAsc = $this->getJson('/api/products?sort_by=name_asc');
        $nameAsc->assertStatus(200);
        $nameData = $nameAsc->json('data.data');
        $this->assertEquals('iPhone 16 Pro Max', $nameData[0]['name']);
        $this->assertEquals('Samsung Galaxy S24 Ultra', $nameData[1]['name']);
    }
}
