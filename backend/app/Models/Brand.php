<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'logo',
        'description',
        'is_active',
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
            'is_active' => 'boolean',
            'status' => 'boolean',
        ];
    }

    /**
     * Backward compatibility accessor for logo.
     */
    public function getLogoAttribute(): ?string
    {
        return $this->attributes['logo_path'] ?? $this->attributes['logo'] ?? null;
    }

    /**
     * Backward compatibility mutator for logo.
     */
    public function setLogoAttribute(?string $value): void
    {
        $this->attributes['logo_path'] = $value;
        $this->attributes['logo'] = $value;
    }

    /**
     * Backward compatibility accessor for status.
     */
    public function getStatusAttribute(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? $this->attributes['status'] ?? true);
    }

    /**
     * Backward compatibility mutator for status.
     */
    public function setStatusAttribute(mixed $value): void
    {
        $boolVal = (bool) $value;
        $this->attributes['is_active'] = $boolVal;
        $this->attributes['status'] = $boolVal;
    }

    /**
     * A brand can have many products.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
