<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Banner extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'name',
        'description',
        'image_path',
        'storage_provider',
        'public_id',
        'mobile_image_path',
        'mobile_public_id',
        'alt_text',
        'cta_label',
        'target_url',
        'position',
        'sort_order',
        'start_at',
        'end_at',
        'is_active',
        'status',
    ];

    protected $appends = [
        'image_url',
        'mobile_image_url',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Resolve public desktop image URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        $path = $this->image_path;
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
            return $path;
        }

        if (($this->storage_provider === 'cloudinary') || str_starts_with($path, 'mobiex/')) {
            $cloudName = config('media.drivers.cloudinary.cloud_name');
            if (! empty($cloudName)) {
                return "https://res.cloudinary.com/{$cloudName}/image/upload/f_auto,q_auto/{$path}";
            }
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return '/'.$path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    /**
     * Resolve public mobile image URL.
     */
    public function getMobileImageUrlAttribute(): ?string
    {
        $path = $this->mobile_image_path;
        if (! $path) {
            return $this->image_url;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://') || str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
            return $path;
        }

        if (($this->storage_provider === 'cloudinary') || str_starts_with($path, 'mobiex/')) {
            $cloudName = config('media.drivers.cloudinary.cloud_name');
            if (! empty($cloudName)) {
                return "https://res.cloudinary.com/{$cloudName}/image/upload/f_auto,q_auto/{$path}";
            }
        }

        if (str_starts_with($path, '/storage/')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            return '/'.$path;
        }

        return '/storage/'.ltrim($path, '/');
    }

    /**
     * Fallback for display name.
     */
    public function getAdminNameAttribute(): string
    {
        return ! empty($this->attributes['name']) ? $this->attributes['name'] : ($this->attributes['title'] ?? 'Banner');
    }

    /**
     * Scope only active and within schedule window.
     */
    public function scopeLive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'inactive');
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('start_at')->orWhere('start_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('end_at')->orWhere('end_at', '>=', $now);
            });
    }
}
