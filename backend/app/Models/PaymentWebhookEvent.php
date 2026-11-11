<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentWebhookEvent extends Model
{
    use HasFactory;

    protected $table = 'payment_webhook_events';

    public $timestamps = false;

    protected $fillable = [
        'payment_id',
        'method',
        'event_key',
        'provider_reference',
        'signature_verified',
        'processing_status',
        'event_payload',
        'error_message',
        'received_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'signature_verified' => 'boolean',
            'event_payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
