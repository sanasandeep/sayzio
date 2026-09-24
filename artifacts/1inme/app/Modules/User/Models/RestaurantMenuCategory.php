<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantMenuCategory extends Model
{
    protected $fillable = [
        'menu_id', 'parent_id', 'name', 'description', 'sort_order', 'is_active',
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

    /** The section this one sits inside, or null when it IS a section. */
    public function parent()
    {
        return $this->belongsTo(RestaurantMenuCategory::class, 'parent_id');
    }

    /** Sub-sections, one level only. See MenuTree::MAX_DEPTH. */
    public function children()
    {
        return $this->hasMany(RestaurantMenuCategory::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }
}
