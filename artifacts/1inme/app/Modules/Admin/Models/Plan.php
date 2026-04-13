<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'monthly_price', 'annual_price',
        'trial_days', 'features', 'is_default', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'monthly_price' => 'decimal:2',
            'annual_price' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(\App\Modules\User\Models\User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
