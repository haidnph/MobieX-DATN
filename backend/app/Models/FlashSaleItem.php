<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FlashSaleItem extends Model
{
    use HasFactory;

    protected $table = 'flash_sale_items';

    protected $fillable = [
        'flash_sale_id',
        'product_variant_id',
        'sale_price',
        'quantity_limit',
        'quantity_reserved',
        'quantity_sold',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'quantity_limit' => 'integer',
            'quantity_reserved' => 'integer',
            'quantity_sold' => 'integer',
        ];
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'product_variant_id', 'product_variant_id');
    }
}
