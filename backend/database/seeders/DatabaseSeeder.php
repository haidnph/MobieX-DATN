<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed canonical baseline reference and rich mock demonstration data.
     */
    public function run(): void
    {
        // Keep canonical authorization data, but never seed demo users or
        // known credentials into a production database.
        $this->call(RolePermissionSeeder::class);

        if (! app()->environment('local', 'testing')) {
            $this->command?->warn(
                'Skipped demo data seeders outside local/testing environments.'
            );

            return;
        }

        $this->call([
            CategorySeeder::class,
            BrandSeeder::class,
            AttributeSeeder::class,
            ProductSpecificationDefinitionSeeder::class,
            ShippingMethodSeeder::class,
            AdminUserSeeder::class,
            CustomerUserSeeder::class,
            BannerSeeder::class,
            CouponSeeder::class,
            ProductCatalogSeeder::class,
            HomepageSectionSeeder::class,
            OrderAndReviewSeeder::class,
            DemoDataSeeder::class,
            ProductSampleDataSeeder::class,
            ProductEdgeCaseSeeder::class,
        ]);
    }
}
