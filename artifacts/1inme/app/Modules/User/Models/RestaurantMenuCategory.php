<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantMenuCategory extends Model
{
    protected $fillable = [
        'menu_id', 'name', 'description', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function menu()
    {
        return $this->belongsTo(RestaurantMenu::class, 'menu_id');
    }

    public function items()
    {
        return $this->hasMany(RestaurantMenuItem::class, 'category_id')->orderBy('sort_order')->orderBy('id');
    }
}
