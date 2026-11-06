<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewImage extends Model
{
    use HasFactory;

    protected $table = 'review_images';

    public const UPDATED_AT = null;

    protected $fillable = [
        'review_id',
        'image_path',
        'alt_text',
        'created_at',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
