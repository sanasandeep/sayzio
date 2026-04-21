<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class CoinPackage extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'coin_amount', 'bonus_coins',
        'status', 'is_archived', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'coin_amount' => 'integer',
            'bonus_coins' => 'integer',
            'is_archived' => 'boolean',
        ];
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
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Total coins customer receives = base + bonus. */
    public function totalCoins(): int
    {
        return (int) $this->coin_amount + (int) $this->bonus_coins;
    }
}
