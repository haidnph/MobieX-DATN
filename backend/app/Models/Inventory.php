<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;

    protected $table = 'inventories';

    protected $fillable = [
        'product_variant_id',
        'variant_id',
        'on_hand_quantity',
        'quantity',
        'reserved_quantity',
        'min_stock',
    ];

    protected function casts(): array
    {
        return [
            'on_hand_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'available_quantity' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    public function getVariantIdAttribute(): ?int
    {
        return $this->attributes['product_variant_id'] ?? null;
    }

    public function setVariantIdAttribute(?int $value): void
    {
        $this->attributes['product_variant_id'] = $value;
    }

    public function getQuantityAttribute(): int
    {
        return (int) ($this->attributes['on_hand_quantity'] ?? 0);
    }

    public function setQuantityAttribute(int $value): void
    {
        $this->attributes['on_hand_quantity'] = $value;
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
