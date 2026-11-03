<?php

namespace Tests\Feature\Catalog;

use App\Models\Brand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandReadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Guests can retrieve a paginated list of visible brands.
     *
     * Hidden brands must not appear.
     * Records must be ordered by name and then ID.
     */
    public function test_guest_can_list_visible_brands_with_pagination(): void
    {
        $firstApple = Brand::factory()->create([
            'name' => 'Apple',
            'slug' => 'apple-first',
            'status' => true,
        ]);

        $secondApple = Brand::factory()->create([
            'name' => 'Apple',
            'slug' => 'apple-second',
            'status' => true,
        ]);

        // Add 14 more visible brands to exceed the default page size.
        foreach (range(1, 14) as $index) {
            Brand::factory()->create([
                'name' => sprintf('Brand %02d', $index),
                'slug' => sprintf('brand-%02d', $index),
                'status' => true,
            ]);
        }

        Brand::factory()->create([
            'name' => 'Hidden Brand',
            'slug' => 'hidden-brand',
            'status' => false,
        ]);

        $response = $this->getJson('/api/brands');

        $response->assertOk()
            ->assertJsonPath(
                'message',
                'Brands retrieved successfully.',
            )
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.per_page', 15)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath(
                'data.0.id',
                $firstApple->getKey(),
            )
            ->assertJsonPath(
                'data.1.id',
                $secondApple->getKey(),
            )
            ->assertJsonPath(
                'data.0.name',
                'Apple',
            )
            ->assertJsonMissing([
                'slug' => 'hidden-brand',
            ])
            ->assertJsonMissingPath('data.0.products');

        $this->assertCount(
            15,
            $response->json('data'),
        );
    }

    /**
     * The page query parameter must select the requested page.
     */
    public function test_guest_can_request_second_page_of_brands(): void
    {
        foreach (range(1, 16) as $index) {
            Brand::factory()->create([
                'name' => sprintf('Brand %02d', $index),
                'slug' => sprintf('brand-%02d', $index),
                'status' => true,
            ]);
        }

        $response = $this->getJson('/api/brands?page=2');

        $response->assertOk()
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 16)
            ->assertJsonPath('meta.per_page', 15);

        $this->assertCount(
            1,
            $response->json('data'),
        );
    }

    /**
     * Guests can retrieve a visible brand by ID.
     */
    public function test_guest_can_retrieve_visible_brand_details(): void
    {
        $brand = Brand::factory()->create([
            'name' => 'Apple',
            'slug' => 'apple',
            'logo' => 'brands/apple.png',
            'description' => 'Apple smartphone brand.',
            'status' => true,
        ]);

        $this->getJson(
            '/api/brands/'.$brand->getKey(),
        )
            ->assertOk()
            ->assertJsonPath(
                'message',
                'Brand retrieved successfully.',
            )
            ->assertJsonPath(
                'data.brand.id',
                $brand->getKey(),
            )
            ->assertJsonPath(
                'data.brand.name',
                'Apple',
            )
            ->assertJsonPath(
                'data.brand.slug',
                'apple',
            )
            ->assertJsonPath(
                'data.brand.logo',
                'brands/apple.png',
            )
            ->assertJsonPath(
                'data.brand.description',
                'Apple smartphone brand.',
            )
            ->assertJsonPath(
                'data.brand.status',
                true,
            )
            ->assertJsonStructure([
                'message',
                'data' => [
                    'brand' => [
                        'id',
                        'name',
                        'slug',
                        'logo',
                        'description',
                        'status',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    /**
     * Hidden brands must not be exposed by the detail endpoint.
     */
    public function test_hidden_brand_returns_not_found(): void
    {
        $brand = Brand::factory()->create([
            'slug' => 'hidden-brand',
            'status' => false,
        ]);

        $this->getJson(
            '/api/brands/'.$brand->getKey(),
        )->assertNotFound();
    }

    /**
     * A nonexistent brand must return 404.
     */
    public function test_missing_brand_returns_not_found(): void
    {
        $this->getJson('/api/brands/999999')
            ->assertNotFound();
    }
}
