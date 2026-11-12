<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSpecification extends Model
{
    use HasFactory;

    protected $table = 'product_specifications';

    protected $fillable = [
        'product_id',
        'specification_definition_id',
        'value_text',
        'numeric_value',
        'value_boolean',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'numeric_value' => 'decimal:4',
            'value_boolean' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ProductSpecificationDefinition::class, 'specification_definition_id');
    }
}
