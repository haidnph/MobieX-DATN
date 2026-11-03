<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'logo',
        'description',
        'status',
    ];

    /**
     * Cast brand attributes to their expected PHP types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    /**
     * A brand can have many products.
     *
     * The Product/Stock module owns the products table
     * and its migration.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
