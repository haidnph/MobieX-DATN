<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnItem extends Model
{
    protected $table = 'return_request_items';

    protected $fillable = [
        'return_request_id',
        'order_item_id',
        'quantity',
        'inspection_condition',
        'inspection_note',
        'is_restocked',
        'restocked_at',
        'refund_amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'is_restocked' => 'boolean',
            'restocked_at' => 'datetime',
            'refund_amount' => 'decimal:2',
        ];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
