<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasFactory;

    protected $hidden = [
        'idempotency_key',
        'response_data',
        'refund_required',
        'gateway_succeeded_at',
        'refund_note',
        'refund_confirmed_by',
    ];

    protected $fillable = [
        'order_id',
        'transaction_code',
        'idempotency_key',
        'method',
        'amount',
        'currency_code',
        'status',
        'gateway_transaction_id',
        'response_data',
        'paid_at',
        'refund_required',
        'gateway_succeeded_at',
        'refunded_at',
        'refund_transaction_id',
        'refund_note',
        'refund_confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'response_data' => 'array',
            'paid_at' => 'datetime',
            'refund_required' => 'boolean',
            'gateway_succeeded_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function webhookEvents(): HasMany
    {
        return $this->hasMany(PaymentWebhookEvent::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(PaymentRefund::class);
    }
}
