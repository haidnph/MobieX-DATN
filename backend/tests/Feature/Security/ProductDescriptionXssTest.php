<?php

namespace Tests\Feature\Security;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductDescriptionXssTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_api_sanitizes_legacy_html_before_returning_it(): void
    {
        $category = Category::factory()->create();
        $brand = Brand::factory()->create();
        $product = Product::create([
            'category_id' => $category->id,
            'brand_id' => $brand->id,
            'product_type' => 'phone',
            'name' => 'Safe product',
            'slug' => 'safe-product',
            'description' => '<p>Safe description</p>',
            'status' => 'active',
        ]);

        // Simulate a legacy database value created before sanitization existed.
        DB::table('products')->where('id', $product->id)->update([
            'description' => '<p onclick="alert(1)">Safe<script>alert(2)</script></p>',
        ]);

        $this->getJson('/api/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.description', '<p>Safe</p>');
    }

    public function test_product_model_sanitizes_description_before_storage(): void
    {
        $product = new Product(['description' => '<p onmouseover="alert(1)">Safe</p>']);

        $this->assertSame('<p>Safe</p>', $product->getAttributes()['description']);
        $this->assertSame('<p>Safe</p>', $product->description);
    }
}
