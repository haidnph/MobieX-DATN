<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Flash Sale campaign.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $status draft|scheduled|active|ended|cancelled
 * @property Carbon $start_at
 * @property Carbon $end_at
 */
class FlashSale extends Model
{
    use HasFactory;

    protected $table = 'flash_sales';

    protected $fillable = [
        'created_by_user_id',
        'name',
        'slug',
        'description',
        'start_at',
        'end_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    // ── Relationships ──────────────────────────────────────────────────────────

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FlashSaleItem::class, 'flash_sale_id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('start_at', '<=', now())
            ->where('end_at', '>=', now());
    }
}
