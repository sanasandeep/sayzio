<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class Addon extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'type',
        'monthly_price', 'annual_price',
        'monthly_price_secondary', 'annual_price_secondary',
        'features', 'metadata', 'status', 'is_archived', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'metadata' => 'array',
            'monthly_price' => 'decimal:2',
            'annual_price' => 'decimal:2',
            'monthly_price_secondary' => 'decimal:2',
            'annual_price_secondary' => 'decimal:2',
            'is_archived' => 'boolean',
        ];
    }

    public const TYPES = ['recurring', 'one_time', 'metered'];

    public function plans()
    {
        return $this->belongsToMany(Plan::class, 'addon_plan');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_archived', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
