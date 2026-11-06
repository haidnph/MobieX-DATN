<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image_path',
        'image',
        'sort_order',
        'is_active',
        'status',
        'is_featured',
        'featured_sort_order',
    ];

    /**
     * Cast category attributes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'status' => 'boolean',
            'is_featured' => 'boolean',
            'featured_sort_order' => 'integer',
        ];
    }

    /**
     * Backward compatibility accessor for image.
     */
    public function getImageAttribute(): ?string
    {
        return $this->attributes['image_path'] ?? $this->attributes['image'] ?? null;
    }

    /**
     * Backward compatibility mutator for image.
     */
    public function setImageAttribute(?string $value): void
    {
        $this->attributes['image_path'] = $value;
        $this->attributes['image'] = $value;
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
     * A category can have many products.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * A category can have multiple child categories.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Retrieve the parent category.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * A category can have multiple attributes assigned directly to it.
     *
     * @return BelongsToMany<Attribute, $this>
     */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(
            Attribute::class,
            'attribute_category',
            'category_id',
            'attribute_id',
        )->withPivot(['is_required', 'is_variant_axis', 'sort_order']);
    }
}
