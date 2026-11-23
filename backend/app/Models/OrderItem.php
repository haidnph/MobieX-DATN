<?php

namespace App\Models;

use App\Services\MediaStorageService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    use HasFactory;

    protected $table = 'order_items';

    protected $hidden = ['cost_price_snapshot'];

    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'flash_sale_id',
        'product_name',
        'variant_name',
        'sku',
        'image_path',
        'price',
        'cost_price_snapshot',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost_price_snapshot' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function flashSale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class, 'flash_sale_id');
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class, 'order_item_id');
    }

    /**
     * Get the resolved public/CDN URL for the item image.
     * Guaranteed to return a publicly accessible, valid image URL for email and storefront.
     */
    public function getImageUrlAttribute(): ?string
    {
        $path = $this->image_path;

        if (empty($path)) {
            $path = $this->variant?->thumbnail
                ?? $this->product?->thumbnail
                ?? $this->product?->primary_image_url;
        }

        if (empty($path)) {
            return 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=400&auto=format&fit=crop';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            // In email contexts, localhost or private IP addresses cannot be fetched by email proxies
            if (str_contains($path, 'localhost') || str_contains($path, '127.0.0.1')) {
                return 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=400&auto=format&fit=crop';
            }

            return $path;
        }

        try {
            $resolved = app(MediaStorageService::class)->url($path);
            if (str_contains($resolved, 'localhost') || str_contains($resolved, '127.0.0.1')) {
                return 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=400&auto=format&fit=crop';
            }

            return $resolved;
        } catch (\Throwable) {
            return 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?q=80&w=400&auto=format&fit=crop';
        }
    }
}
