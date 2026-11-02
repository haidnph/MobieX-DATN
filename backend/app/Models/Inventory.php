<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventories';

    protected $fillable = [
        'variant_id',
        'quantity',
        'reserved_quantity',
        'min_stock',
    ];

    public $timestamps = false;

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
