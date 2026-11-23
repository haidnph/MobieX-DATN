<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailSlugTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

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

        $this->product = Product::create([
            'name' => 'iPhone 16 Pro Max',
            'slug' => 'iphone-16-pro-max',
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'status' => 'active',
            'product_type' => 'phone',
        ]);
    }

    public function test_can_view_product_detail_by_slug(): void
    {
        $response = $this->getJson("/api/products/{$this->product->slug}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $this->product->id)
            ->assertJsonPath('data.slug', 'iphone-16-pro-max')
            ->assertJsonPath('data.name', 'iPhone 16 Pro Max');
    }

    public function test_can_view_product_detail_by_numeric_id(): void
    {
        $response = $this->getJson("/api/products/{$this->product->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.id', $this->product->id)
            ->assertJsonPath('data.slug', 'iphone-16-pro-max');
    }

    public function test_product_detail_sanitizes_legacy_description_html_before_returning_it(): void
    {
        // Query builder update bypasses the model mutator to represent legacy rows.
        Product::query()->whereKey($this->product->id)->update([
            'description' => '<p onclick="alert(1)">Safe text<script>alert(2)</script></p>',
        ]);

        $response = $this->getJson("/api/products/{$this->product->id}");

        $response->assertOk()
            ->assertJsonPath('data.description', '<p>Safe text</p>');
        $this->assertStringNotContainsString('alert', $response->getContent());
    }

    public function test_view_product_detail_returns_404_for_invalid_slug(): void
    {
        $response = $this->getJson('/api/products/non-existent-product-slug-xyz');

        $response->assertStatus(404)
            ->assertJsonPath('status', false);
    }

    public function test_view_product_detail_returns_404_for_invalid_id(): void
    {
        $response = $this->getJson('/api/products/999999');

        $response->assertStatus(404)
            ->assertJsonPath('status', false);
    }

    public function test_can_fetch_reviews_by_product_slug(): void
    {
        $response = $this->getJson("/api/products/{$this->product->slug}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page']);
    }

    public function test_can_fetch_reviews_by_product_id(): void
    {
        $response = $this->getJson("/api/products/{$this->product->id}/reviews");

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page']);
    }

    public function test_can_fetch_compatible_phones_by_product_slug(): void
    {
        $response = $this->getJson("/api/products/{$this->product->slug}/compatible-phones");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data', []);
    }
}
