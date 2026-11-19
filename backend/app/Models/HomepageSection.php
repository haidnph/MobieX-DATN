<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomepageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'section_key',
        'title',
        'subtitle',
        'section_type',
        'layout_type',
        'items_limit',
        'sort_order',
        'is_active',
        'settings',
        'published_settings',
    ];

    protected function casts(): array
    {
        return [
            'items_limit' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'settings' => 'array',
            'published_settings' => 'array',
        ];
    }
}
