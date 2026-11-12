<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'brand_id',
        'category_id',
        'product_type',
        'name',
        'slug',
        'description',
        'status',
    ];

    protected $appends = [
        'min_price',
        'max_price',
        'thumbnail',
    ];

    public function getThumbnailAttribute(): ?string
    {
        if ($this->relationLoaded('images')) {
            $primary = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

            return $primary?->image_path ?? $primary?->image_url ?? null;
        }

        $primary = $this->images()->where('is_primary', true)->first() ?? $this->images()->first();

        return $primary?->image_path ?? $primary?->image_url ?? null;
    }

    public function getMinPriceAttribute(): float
    {
        if ($this->relationLoaded('variants')) {
            if ($this->variants->isNotEmpty()) {
                $minVariant = $this->variants->where('is_active', true)->min('price') ?? $this->variants->min('price');
                if ($minVariant !== null && (float) $minVariant > 0) {
                    return (float) $minVariant;
                }
            }

            return (float) ($this->attributes['base_price'] ?? 0.0);
        }

        $minVariant = $this->variants()->where('is_active', true)->min('price') ?? $this->variants()->min('price');
        if ($minVariant !== null && (float) $minVariant > 0) {
            return (float) $minVariant;
        }

        return (float) ($this->attributes['base_price'] ?? 0.0);
    }

    public function getMaxPriceAttribute(): ?float
    {
        if ($this->relationLoaded('variants')) {
            if ($this->variants->isNotEmpty()) {
                $maxCompare = $this->variants->max('compare_price');
                if ($maxCompare && (float) $maxCompare > 0) {
                    return (float) $maxCompare;
                }
                $maxPrice = $this->variants->max('price');
                return ($maxPrice && (float) $maxPrice > 0) ? (float) $maxPrice : null;
            }

            return null;
        }

        $maxCompare = $this->variants()->max('compare_price');
        if ($maxCompare && (float) $maxCompare > 0) {
            return (float) $maxCompare;
        }
        $maxPrice = $this->variants()->max('price');

        return ($maxPrice && (float) $maxPrice > 0) ? (float) $maxPrice : null;
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_id')
            ->orderBy('sort_order');
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class);
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(ProductCompatibility::class, 'product_id');
    }

    public function attributeValues(): HasMany
    {
        return $this->hasMany(ProductAttributeValue::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
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
