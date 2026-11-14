<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductImage extends Model
{
    use HasFactory;

    protected $table = 'product_images';

    // Bảng chỉ có created_at, không có updated_at.
    public const UPDATED_AT = null;

    protected $fillable = [
        'product_id',
        'product_variant_id',
        'variant_id',
        'attribute_value_id',
        'image_path',
        'image_url',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    protected $appends = [
        'image_url',
    ];

    public function getVariantIdAttribute(): ?int
    {
        return $this->attributes['product_variant_id'] ?? null;
    }

    public function setVariantIdAttribute(?int $value): void
    {
        $this->attributes['product_variant_id'] = $value;
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->attributes['image_path'] ?? null;
    }

    public function setImageUrlAttribute(?string $value): void
    {
        $this->attributes['image_path'] = $value;
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'attribute_value_id');
    }
}
