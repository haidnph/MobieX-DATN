<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $table = 'inventory_transactions';

    public const UPDATED_AT = null;

    protected $fillable = [
        'product_variant_id',
        'variant_id',
        'user_id',
        'type',
        'transaction_type',
        'quantity_delta',
        'quantity',
        'reserved_delta',
        'unit_cost',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'variant_id' => 'integer',
            'product_variant_id' => 'integer',
            'user_id' => 'integer',
            'quantity' => 'integer',
            'quantity_delta' => 'integer',
            'reserved_delta' => 'integer',
            'unit_cost' => 'decimal:2',
            'reference_id' => 'integer',
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

    public function getTransactionTypeAttribute(): ?string
    {
        return $this->attributes['type'] ?? null;
    }

    public function setTransactionTypeAttribute(?string $value): void
    {
        $this->attributes['type'] = $value;
    }

    public function getQuantityAttribute(): int
    {
        return (int) ($this->attributes['quantity_delta'] ?? 0);
    }

    public function setQuantityAttribute(int $value): void
    {
        $this->attributes['quantity_delta'] = $value;
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
