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
        'specification_name',
        'specification_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Một thông số kỹ thuật thuộc về một sản phẩm.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
