<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnHistory extends Model
{
    public $timestamps = false;
    protected $fillable = ['return_request_id', 'user_id', 'from_status', 'status', 'note', 'created_at'];
    protected function casts(): array { return ['created_at' => 'datetime']; }
    public function returnRequest(): BelongsTo { return $this->belongsTo(ReturnRequest::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}