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
        $this->call([
            RolePermissionSeeder::class,
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
            OrderAndReviewSeeder::class,
            DemoDataSeeder::class,
            ProductSampleDataSeeder::class,
        ]);
    }
}
