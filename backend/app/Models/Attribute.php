<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'display_type',
        'type',
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
     * Backward compatibility accessor for type.
     */
    public function getTypeAttribute(): string
    {
        return $this->attributes['display_type'] ?? $this->attributes['type'] ?? 'select';
    }

    /**
     * Backward compatibility mutator for type.
     */
    public function setTypeAttribute(?string $value): void
    {
        $this->attributes['display_type'] = $value ?? 'select';
        $this->attributes['type'] = $value ?? 'select';
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
     * An attribute has many selectable values.
     *
     * @return HasMany<AttributeValue, $this>
     */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class);
    }

    /**
     * An attribute can be assigned to multiple categories.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'attribute_category',
            'attribute_id',
            'category_id',
        )->withPivot(['is_required', 'is_variant_axis', 'sort_order']);
    }
}
