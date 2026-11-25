<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnStatusHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'return_id',
        'user_id',
        'from_status',
        'status',
        'note',
    ];

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(
            ReturnRequest::class,
            'return_id'
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}