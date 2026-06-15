<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantMenuItem extends Model
{
    protected $fillable = [
        'menu_id', 'category_id', 'name', 'description', 'price', 'currency',
        'photo_url', 'sort_order', 'is_sold_out', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'       => 'decimal:2',
            'is_sold_out' => 'boolean',
            'is_active'   => 'boolean',
        ];
    }

    public function menu()
    {
        return $this->belongsTo(RestaurantMenu::class, 'menu_id');
    }

    public function category()
    {
        return $this->belongsTo(RestaurantMenuCategory::class, 'category_id');
    }
}
