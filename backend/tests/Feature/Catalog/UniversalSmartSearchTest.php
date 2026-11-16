<?php

namespace Tests\Feature\Catalog;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductSpecification;
use App\Models\ProductSpecificationDefinition;
use App\Models\ProductVariant;
use App\Models\ProductVariantAttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UniversalSmartSearchTest extends TestCase
{
    use RefreshDatabase;

    protected Brand $apple;

    protected Brand $samsung;

    protected Category $phoneCategory;

    protected Category $chargerCategory;

    protected Product $iphone16pm;

    protected Product $s24ultra;

    protected Product $galaxyA15;

    protected Product $ankerCharger;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('search_all_brands_v2');
        Cache::forget('search_all_categories_v2');

        // Brands
        $this->apple = Brand::create(['name' => 'Apple', 'slug' => 'apple', 'is_active' => true]);
        $this->samsung = Brand::create(['name' => 'Samsung', 'slug' => 'samsung', 'is_active' => true]);

        // Categories
        $this->phoneCategory = Category::create(['name' => 'Điện thoại', 'slug' => 'dien-thoai', 'is_active' => true]);
        $this->chargerCategory = Category::create(['name' => 'Sạc', 'slug' => 'sac', 'is_active' => true]);

        // Products
        $this->iphone16pm = Product::create([
            'name' => 'iPhone 16 Pro Max',
            'slug' => 'iphone-16-pro-max',
            'product_type' => 'phone',
            'category_id' => $this->phoneCategory->id,
            'brand_id' => $this->apple->id,
            'base_price' => 34990000,
            'status' => 'active',
        ]);
        $v1 = ProductVariant::create([
            'product_id' => $this->iphone16pm->id,
            'sku' => 'IP16PM-256-TEST',
            'price' => 34990000,
            'is_active' => true,
        ]);

        $this->s24ultra = Product::create([
            'name' => 'Samsung Galaxy S24 Ultra',
            'slug' => 'samsung-galaxy-s24-ultra',
            'product_type' => 'phone',
            'category_id' => $this->phoneCategory->id,
            'brand_id' => $this->samsung->id,
            'base_price' => 29990000,
            'status' => 'active',
        ]);
        ProductVariant::create([
            'product_id' => $this->s24ultra->id,
            'sku' => 'S24U-256-TEST',
            'price' => 29990000,
            'is_active' => true,
        ]);

        $this->galaxyA15 = Product::create([
            'name' => 'Samsung Galaxy A15',
            'slug' => 'samsung-galaxy-a15',
            'product_type' => 'phone',
            'category_id' => $this->phoneCategory->id,
            'brand_id' => $this->samsung->id,
            'base_price' => 4500000,
            'status' => 'active',
        ]);
        ProductVariant::create([
            'product_id' => $this->galaxyA15->id,
            'sku' => 'A15-128-TEST',
            'price' => 4500000,
            'is_active' => true,
        ]);

        $this->ankerCharger = Product::create([
            'name' => 'Củ sạc Anker 65W GaN Type-C',
            'slug' => 'cu-sac-anker-65w-gan-type-c',
            'product_type' => 'accessory',
            'category_id' => $this->chargerCategory->id,
            'brand_id' => $this->apple->id, // or accessory brand
            'base_price' => 850000,
            'status' => 'active',
        ]);
        ProductVariant::create([
            'product_id' => $this->ankerCharger->id,
            'sku' => 'ANK-65W-TEST',
            'price' => 850000,
            'is_active' => true,
        ]);

        // Attribute: storage 256GB
        $storageAttr = Attribute::firstOrCreate(['code' => 'storage'], ['name' => 'Dung lượng']);
        $storage256 = AttributeValue::firstOrCreate(
            ['attribute_id' => $storageAttr->id, 'value' => '256GB'],
            ['label' => '256GB']
        );
        ProductVariantAttributeValue::firstOrCreate([
            'product_variant_id' => $v1->id,
            'attribute_id' => $storageAttr->id,
            'attribute_value_id' => $storage256->id,
        ]);

        // Spec definition: charging_power
        $wattDef = ProductSpecificationDefinition::firstOrCreate(
            ['code' => 'charging_power'],
            ['name' => 'Công suất sạc', 'unit' => 'W', 'applies_to' => 'accessory']
        );
        ProductSpecification::create([
            'product_id' => $this->ankerCharger->id,
            'specification_definition_id' => $wattDef->id,
            'numeric_value' => 65,
        ]);
    }

    public function test_exact_model_alias_resolution_for_iphone(): void
    {
        $response = $this->getJson('/api/products?q=ip16pm');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $json = $response->json();
        $this->assertNotEmpty($json['data']['data']);
        $this->assertEquals('iPhone 16 Pro Max', $json['intent']['model_alias']);
        $this->assertEquals('iPhone 16 Pro Max', $json['data']['data'][0]['name']);
    }

    public function test_samsung_model_alias_resolution(): void
    {
        $response = $this->getJson('/api/products?q=s24u');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $json = $response->json();
        $this->assertNotEmpty($json['data']['data']);
        $this->assertEquals('Samsung Galaxy S24 Ultra', $json['intent']['model_alias']);
        $this->assertStringContainsString('S24 Ultra', $json['data']['data'][0]['name']);
    }

    public function test_typo_tolerance_detection_and_recovery(): void
    {
        $response = $this->getJson('/api/products?q=samsng%20s24');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertTrue($json['intent']['has_typo']);
        $this->assertStringContainsString('samsung', strtolower($json['intent']['suggested_query']));
        $this->assertNotEmpty($json['data']['data']);
        // The results should be Samsung products
        $this->assertEquals('Samsung', $json['data']['data'][0]['brand']['name']);
    }

    public function test_brand_synonym_tao_matches_apple(): void
    {
        $response = $this->getJson('/api/products?q=táo%20256gb');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals('Apple', $json['intent']['brand']['name']);
        $this->assertEquals('256GB', $json['intent']['storage']);
    }

    public function test_natural_language_price_budget_parsing(): void
    {
        $response = $this->getJson('/api/products?q=điện%20thoại%20dưới%2015%20triệu');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals('Điện thoại', $json['intent']['category']['name']);
        $this->assertEquals(15000000, $json['intent']['max_price']);
        // Galaxy A15 should be included (price 4.5M), while iPhone 16 Pro Max (34.9M) excluded
        $names = array_column($json['data']['data'], 'name');
        $this->assertContains('Samsung Galaxy A15', $names);
        $this->assertNotContains('iPhone 16 Pro Max', $names);
    }

    public function test_hardware_specifications_parsing_wattage_and_connector(): void
    {
        $response = $this->getJson('/api/products?q=sạc%2065w%20type-c');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertEquals(65, $json['intent']['specs']['charging_wattage']);
        $this->assertEquals('Type-C', $json['intent']['specs']['connector_type']);
        $this->assertNotEmpty($json['data']['data']);
        $this->assertEquals('Củ sạc Anker 65W GaN Type-C', $json['data']['data'][0]['name']);
    }

    public function test_search_suggestions_autocomplete_payload(): void
    {
        $response = $this->getJson('/api/products?suggest=1&keyword=samsng');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $json = $response->json();
        $this->assertArrayHasKey('intent', $json['data']);
        $this->assertTrue($json['data']['has_typo']);
        $this->assertNotEmpty($json['data']['brands']);
        $this->assertEquals('Samsung', $json['data']['brands'][0]['name']);
    }

    public function test_dismissible_exclude_criteria(): void
    {
        // With max_price <= 15M, total is 1 (Galaxy A15)
        $resp1 = $this->getJson('/api/products?q=điện%20thoại%20dưới%2015%20triệu');
        $resp1->assertStatus(200);
        $this->assertEquals(1, $resp1->json('data.total'));

        // With exclude_criteria=price, the price limit is ignored, total is 3 (all 3 phones)
        $resp2 = $this->getJson('/api/products?q=điện%20thoại%20dưới%2015%20triệu&exclude_criteria=price');
        $resp2->assertStatus(200);
        $this->assertEquals(3, $resp2->json('data.total'));
    }
}
