<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BrandSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that the brands table has the expected columns.
     */
    public function test_brands_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('brands', [
            'id',
            'name',
            'slug',
            'logo',
            'description',
            'status',
            'created_at',
            'updated_at',
        ]));
    }

    /**
     * Verify the default visibility and nullable brand fields.
     */
    public function test_brand_has_correct_database_defaults(): void
    {
        $brand = Brand::query()->create([
            'name' => 'Apple',
            'slug' => 'apple',
        ]);

        $brand->refresh();

        $this->assertTrue($brand->status);

        $this->assertNull($brand->logo);

        $this->assertNull($brand->description);
    }

    /**
     * Verify slug uniqueness is enforced by the database.
     */
    public function test_brand_slug_must_be_unique(): void
    {
        Brand::factory()->create([
            'slug' => 'apple',
        ]);

        $this->expectException(QueryException::class);

        Brand::factory()->create([
            'slug' => 'apple',
        ]);
    }

    /**
     * Verify the existing Product module relationship remains intact.
     *
     * This test does not require or create the products table.
     */
    public function test_brand_products_relationship_uses_brand_id(): void
    {
        $brand = new Brand;

        $products = $brand->products();

        $this->assertSame(
            'brand_id',
            $products->getForeignKeyName(),
        );

        $this->assertSame(
            'products',
            $products->getRelated()->getTable(),
        );
    }
}
