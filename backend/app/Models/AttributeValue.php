<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AttributeValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'attribute_id',
        'code',
        'value',
        'color_code',
        'sort_order',
        'is_active',
        'status',
    ];

    /**
     * Cast model attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'status' => 'boolean',
        ];
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
     * The attribute that owns this value.
     *
     * @return BelongsTo<Attribute, $this>
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Relationship with ProductVariant.
     *
     * @return BelongsToMany<ProductVariant, $this>
     */
    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVariant::class,
            'variant_attribute_values',
            'attribute_value_id',
            'variant_id',
        );
    }
}
