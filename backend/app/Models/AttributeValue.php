<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeValue extends Model
{
     protected $fillable = [
        'attribute_id',
        'value',
        'color_code',
        'sort_order',
        'status',
    ];

    // Giá trị này thuộc về một thuộc tính
    public function attribute()
    {
        return $this->belongsTo(Attribute::class);
    }

    // Một giá trị thuộc tính có thể được dùng cho nhiều biến thể
    public function variants()
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'variant_attribute_values',
            'attribute_value_id',
            'variant_id'
        );
    }
}
