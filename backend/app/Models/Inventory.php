<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Inventory extends Model
{
    use HasFactory;
    public const CREATED_AT = null;
    protected $table = 'inventories';

    protected $fillable = [
        'variant_id',
        'quantity',
        'reserved_quantity',
        'min_stock',
    ];

    protected function casts(): array
    {
        return [
            'variant_id' => 'integer',
            'quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'available_quantity' => 'integer',
            'min_stock' => 'integer',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
