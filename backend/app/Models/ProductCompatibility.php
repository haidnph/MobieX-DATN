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
        'product_id',
        'compatible_product_id',
        'note',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function compatibleProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'compatible_product_id');
    }
}
