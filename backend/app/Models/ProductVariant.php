<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'price',
        'compare_price',
        'cost_price',
        'image_url',
        'status',
    ];

    protected $appends = [
        'available_quantity',
        'name',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
        ];
    }

    public function getAvailableQuantityAttribute(): int
    {
        if ($this->relationLoaded('inventory') && $this->inventory) {
            return (int) ($this->inventory->available_quantity ?? 0);
        }

        return 0;
    }

    public function getNameAttribute(): string
    {
        if ($this->relationLoaded('attributeValues') && $this->attributeValues->isNotEmpty()) {
            return $this->attributeValues
                ->pluck('value')
                ->filter()
                ->implode(' - ');
        }

        return "Phiên bản #{$this->id}";
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'variant_attribute_values',
            'variant_id',
            'attribute_value_id'
        );
    }



    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'variant_id');
    }
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'variant_id')
            ->orderBy('sort_order');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class, 'product_variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_variant_id');
    }

    public function flashSaleItems(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class, 'product_variant_id');
    }
}
