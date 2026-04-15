<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class BgTemplate extends Model
{
    protected $fillable = [
        'name', 'slug', 'preview_color', 'css', 'js',
        'category', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }
}
