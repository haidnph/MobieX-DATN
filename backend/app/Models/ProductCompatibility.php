<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCompatibility extends Model
{
    use HasFactory;

    protected $table = 'product_compatibilities';

    public $timestamps = false;

    public $incrementing = false;

    protected $fillable = [
        'accessory_product_id',
        'compatible_phone_product_id',
        'note',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function accessoryProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'accessory_product_id');
    }

    public function compatiblePhoneProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'compatible_phone_product_id');
    }
}
