
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    use HasFactory;

    protected $table = 'coupon_redemptions';

    /**
     * Attributes that can be mass assigned.
     */
    protected $fillable = [
        'coupon_id',
        'order_id',
        'user_id',
        'discount_amount',
        'status',
    ];

    /**
     * Attribute casting.
     */
    protected function casts(): array
    {
        return [
            'coupon_id' => 'integer',
            'order_id' => 'integer',
            'user_id' => 'integer',
            'discount_amount' => 'decimal:2',
        ];
    }

    /**
     * Coupon associated with this redemption.
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class, 'coupon_id');
    }

    /**
     * Order associated with this redemption.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * User who redeemed the coupon.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
