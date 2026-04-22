<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'description',
        'monthly_price', 'annual_price',
        'monthly_price_secondary', 'annual_price_secondary',
        'trial_days', 'grace_days', 'refund_window_days',
        'features', 'metadata', 'is_default', 'is_popular', 'status',
        'is_archived', 'sort_order',
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
            'is_default' => 'boolean',
            'is_popular' => 'boolean',
            'is_archived' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(\App\Modules\User\Models\User::class);
    }

    public function domains()
    {
        return $this->belongsToMany(\App\Modules\User\Models\Domain::class, 'domain_plan');
    }

    public function addons()
    {
        return $this->belongsToMany(Addon::class, 'addon_plan');
    }

    public function prices()
    {
        return $this->morphMany(Price::class, 'priceable');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('is_archived', false);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
