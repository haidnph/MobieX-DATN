<?php

namespace Tests\Feature\Security;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVisibilityApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_hidden_products_are_not_exposed_to_storefront_but_remain_available_to_authorized_admins(): void
    {
        $product = Product::create([
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'product_type' => 'phone',
            'name' => 'Draft Phone',
            'slug' => 'draft-phone',
            'status' => 'draft',
        ]);
        $admin = User::factory()->create(['is_active' => true]);
        $permission = Permission::factory()->create([
            'name' => 'Manage products',
            'slug' => 'products.manage',
        ]);
        $role = Role::factory()->create(['name' => 'Product manager', 'slug' => 'product-manager']);
        $role->permissions()->attach($permission);
        $admin->assignRole($role);

        $this->getJson('/api/products/'.$product->slug)->assertNotFound();
        $this->withToken($admin->createToken('product-admin')->plainTextToken)
            ->getJson('/api/admin/products/'.$product->id)
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_hidden_products_are_excluded_from_storefront_comparison(): void
    {
        $product = Product::create([
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'product_type' => 'phone',
            'name' => 'Inactive Phone',
            'slug' => 'inactive-phone',
            'status' => 'inactive',
        ]);

        $this->getJson('/api/products/compare?ids='.$product->id)
            ->assertOk()
            ->assertJsonCount(0, 'data.products');
    }

    public function test_out_of_stock_products_remain_visible_but_cannot_be_added_to_cart(): void
    {
        $product = Product::create([
            'category_id' => Category::factory()->create()->id,
            'brand_id' => Brand::factory()->create()->id,
            'product_type' => 'phone',
            'name' => 'Unavailable Phone',
            'slug' => 'unavailable-phone',
            'status' => 'out_of_stock',
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'UNAVAILABLE-PHONE-1',
            'option_signature' => 'color:Black',
            'price' => 100000,
            'is_active' => true,
        ]);
        $customer = User::factory()->create(['is_active' => true]);

        $this->getJson('/api/products/'.$product->slug)
            ->assertOk()
            ->assertJsonPath('data.status', 'out_of_stock');

        $this->withToken($customer->createToken('out-of-stock-test')->plainTextToken)
            ->postJson('/api/cart/items', [
                'variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable();
    }
}
