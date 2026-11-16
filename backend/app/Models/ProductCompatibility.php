<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCompatibility extends Model
{
    protected $table = 'product_compatibilities';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'accessory_product_id',
        'compatible_phone_product_id',
        'note',
    ];

    public function accessory(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'accessory_product_id');
    }

    public function compatiblePhone(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'compatible_phone_product_id');
    }
}
