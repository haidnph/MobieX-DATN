<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductSearchApiTest extends TestCase
{
    use RefreshDatabase;

    private Product $phone1;
    private Product $phone2;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create([
            'name' => 'Điện thoại',
            'slug' => 'dien-thoai',
            'status' => true,
        ]);

        $brand = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'status' => true,
        ]);

        $this->phone1 = Product::create([
            'name' => 'iPhone 16 Pro Max',
            'slug' => 'iphone-16-pro-max',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'status' => 'active',
            'base_price' => 34990000,
        ]);

        $this->phone2 = Product::create([
            'name' => 'Samsung Galaxy S24 Ultra',
            'slug' => 'samsung-galaxy-s24-ultra',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'status' => 'active',
            'base_price' => 29990000,
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
}
