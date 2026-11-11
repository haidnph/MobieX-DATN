<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'brand_id',
        'category_id',
        'status',
    ];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function images()
    {
        return $this->hasMany(ProductImage::class, 'product_id')
            ->orderBy('sort_order');
    }
    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class, 'product_id')
            ->orderBy('sort_order');
    }

    // Danh sách sản phẩm tương thích với sản phẩm hiện tại
    public function compatibleProducts(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_compatibilities',
            'product_id',
            'compatible_product_id'
        )->withPivot('note');
    }

    // Danh sách phụ kiện tương thích với sản phẩm hiện tại
    public function compatibleAccessories(): BelongsToMany
    {
        return $this->belongsToMany(
            Product::class,
            'product_compatibilities',
            'compatible_product_id',
            'product_id'
        )->withPivot('note');
    }
}
