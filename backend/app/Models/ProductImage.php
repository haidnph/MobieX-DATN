<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    protected $table = 'product_images';

    protected $fillable = [
        'product_id',
        'variant_id',
        'image_url',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    // Bảng chỉ có created_at, không có updated_at
    public const UPDATED_AT = null;

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}