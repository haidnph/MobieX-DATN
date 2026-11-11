<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\ProductSpecificationDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCompareApiTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Brand $brand;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name' => 'Điện thoại',
            'slug' => 'dien-thoai',
            'status' => true,
        ]);

        $this->brand = Brand::create([
            'name' => 'Apple',
            'slug' => 'apple',
            'status' => true,
        ]);
    }

    private function createSampleProduct(string $name, string $slug): Product
    {
        return Product::create([
            'name' => $name,
            'slug' => $slug,
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'product_type' => 'phone',
            'status' => 'active',
            'base_price' => 20000000,
        ]);
    }

    public function test_compare_fails_when_ids_missing(): void
    {
        $response = $this->getJson('/api/products/compare');

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
            ]);
    }

    public function test_compare_fails_when_ids_invalid(): void
    {
        $response = $this->getJson('/api/products/compare?ids=abc,def');

        $response->assertStatus(422)
            ->assertJson([
                'status' => false,
            ]);
    }

    public function test_compare_returns_products_and_specs_with_comma_separated_ids(): void
    {
        $p1 = $this->createSampleProduct('iPhone 15 Pro', 'iphone-15-pro');
        $p2 = $this->createSampleProduct('iPhone 16 Pro', 'iphone-16-pro');

        $def = ProductSpecificationDefinition::create([
            'code' => 'chipset',
            'name' => 'Vi xử lý',
            'value_type' => 'text',
            'applies_to' => 'phone',
            'is_comparable' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        ProductSpecification::create([
            'product_id' => $p1->id,
            'specification_definition_id' => $def->id,
            'value_text' => 'Apple A17 Pro',
        ]);

        ProductSpecification::create([
            'product_id' => $p2->id,
            'specification_definition_id' => $def->id,
            'value_text' => 'Apple A18 Pro',
        ]);

        $response = $this->getJson("/api/products/compare?ids={$p1->id},{$p2->id}");

        $response->assertStatus(200)
            ->assertJson([
                'status' => true,
            ])
            ->assertJsonCount(2, 'data.products')
            ->assertJsonPath('data.products.0.id', $p1->id)
            ->assertJsonPath('data.products.1.id', $p2->id);

        $this->assertNotEmpty($response->json('data.comparable_definitions'));
    }

    public function test_compare_accepts_array_of_ids(): void
    {
        $p1 = $this->createSampleProduct('iPhone 15', 'iphone-15');
        $p2 = $this->createSampleProduct('iPhone 16', 'iphone-16');

        $response = $this->getJson("/api/products/compare?ids[]={$p1->id}&ids[]={$p2->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.products');
    }

    public function test_compare_caps_at_maximum_four_products(): void
    {
        $products = [];
        for ($i = 1; $i <= 6; $i++) {
            $products[] = $this->createSampleProduct("Phone {$i}", "phone-{$i}");
        }

        $idsString = implode(',', array_map(fn ($p) => $p->id, $products));
        $response = $this->getJson("/api/products/compare?ids={$idsString}");

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data.products');
    }

    public function test_show_product_eager_loads_specifications_with_definition(): void
    {
        $p = $this->createSampleProduct('iPhone 15 Pro Max', 'iphone-15-pro-max');

        $def = ProductSpecificationDefinition::create([
            'code' => 'battery_capacity',
            'name' => 'Dung lượng pin',
            'value_type' => 'number',
            'unit' => 'mAh',
            'applies_to' => 'phone',
            'is_comparable' => true,
            'sort_order' => 4,
            'is_active' => true,
        ]);

        ProductSpecification::create([
            'product_id' => $p->id,
            'specification_definition_id' => $def->id,
            'numeric_value' => 4422,
        ]);

        $response = $this->getJson("/api/products/{$p->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.specifications.0.definition.code', 'battery_capacity')
            ->assertJsonPath('data.specifications.0.definition.name', 'Dung lượng pin')
            ->assertJsonPath('data.specifications.0.definition.unit', 'mAh');
    }
}
