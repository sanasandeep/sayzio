<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class CardTemplate extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'description', 'thumbnail_url',
        'plan_tier', 'is_active', 'sort_order', 'snapshot',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'snapshot' => 'array',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    public static function categories(): array
    {
        return [
            'general' => 'General',
            'hero' => 'Hero',
            'cta' => 'Call to Action',
            'social' => 'Social Proof',
            'contact' => 'Contact',
            'product' => 'Product',
            'event' => 'Event',
            'gallery' => 'Gallery',
        ];
    }
}
