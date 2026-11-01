<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    protected $fillable = [
        'name',
        'code',
        'type',
        'sort_order',
        'status',
    ];

    // Một thuộc tính có nhiều giá trị
    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }
}
