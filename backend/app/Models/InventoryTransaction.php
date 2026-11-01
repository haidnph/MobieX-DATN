<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    protected $table = 'inventory_transactions';

    protected $fillable = [
        'variant_id',
        'user_id',
        'transaction_type',
        'quantity',
        'reference_type',
        'reference_id',
        'note',
    ];

    // Bảng chỉ có created_at, không có updated_at
    public const UPDATED_AT = null;

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}