<?php

namespace App\Models;

use Database\Factories\UserAddressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    /** @use HasFactory<UserAddressFactory> */
    use HasFactory;

    protected $fillable = [
        'label',
        'full_name',
        'phone',
        'province_code',
        'province_name',
        'ward_code',
        'ward_name',
        'city',
        'district',
        'ward',
        'address_line',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get the owner of this address.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
