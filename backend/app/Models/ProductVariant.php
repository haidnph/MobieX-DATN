<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'sku',
        'option_signature',
        'price',
        'compare_price',
        'cost_price',
        'is_active',
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
            'is_active' => 'boolean',
        ];
    }

    /**
     * Backward compatibility accessor for status.
     */
    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'active' : 'inactive';
    }

    /**
     * Backward compatibility mutator for status.
     */
    public function setStatusAttribute(mixed $value): void
    {
        $this->is_active = in_array($value, [1, '1', true, 'active'], true);
    }

    /**
     * Lấy số lượng có thể bán (available_quantity) từ inventory.
     * Frontend đọc trường này để hiển thị tồn kho.
     */
    public function getAvailableQuantityAttribute(): int
    {
        if ($this->relationLoaded('inventory') && $this->inventory) {
            return (int) ($this->inventory->available_quantity ?? 0);
        }

        return 0;
    }

    /**
     * Tên hiển thị cho biến thể, parse từ option_signature.
     * Ví dụ: "color:Đen|storage:256GB" → "Đen • 256GB"
     */
    public function getNameAttribute(): string
    {
        if (empty($this->option_signature)) {
            return "Phiên bản #{$this->id}";
        }

        return collect(explode('|', $this->option_signature))
            ->map(fn ($part) => trim(explode(':', $part, 2)[1] ?? $part))
            ->filter()
            ->join(' • ');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'product_variant_attribute_values', // pivot table đúng
            'product_variant_id',              // FK của ProductVariant trong pivot
            'attribute_value_id'               // FK của AttributeValue trong pivot
        )->withPivot('attribute_id');
    }

    public function variantAttributeValues(): HasMany
    {
        return $this->hasMany(ProductVariantAttributeValue::class, 'product_variant_id');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class, 'product_variant_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class, 'product_variant_id')
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
