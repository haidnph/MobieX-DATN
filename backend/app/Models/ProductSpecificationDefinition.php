<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductSpecificationDefinition extends Model
{
    use HasFactory;

    protected $table = 'product_specification_definitions';

    protected $fillable = [
        'code',
        'name',
        'value_type',
        'unit',
        'applies_to',
        'is_comparable',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_comparable' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function specifications(): HasMany
    {
        return $this->hasMany(ProductSpecification::class, 'specification_definition_id');
    }
}
