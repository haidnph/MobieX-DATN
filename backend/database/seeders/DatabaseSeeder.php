<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed canonical baseline reference data.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CategorySeeder::class,
            AttributeSeeder::class,
            ProductSpecificationDefinitionSeeder::class,
            ShippingMethodSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
