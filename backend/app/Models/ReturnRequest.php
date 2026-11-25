<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    protected $fillable = [
        'return_code', 'order_id', 'user_id', 'status', 'reason', 'description',
        'customer_note', 'admin_note', 'attachments', 'shipping_carrier',
        'return_tracking_code', 'received_at', 'inspected_at', 'completed_at',
        'rejected_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'received_at' => 'datetime',
            'inspected_at' => 'datetime',
            'completed_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function items(): HasMany { return $this->hasMany(ReturnItem::class); }
    public function histories(): HasMany { return $this->hasMany(ReturnHistory::class)->latest(); }
}