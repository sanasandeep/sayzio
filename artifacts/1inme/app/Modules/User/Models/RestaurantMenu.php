<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantMenu extends Model
{
    public const MODE_DISPLAY = 'display';
    public const MODE_ORDER   = 'order';

    protected $fillable = [
        'link_id', 'user_id', 'mode', 'currency', 'accent_color', 'settings',
    ];

    /**
     * New menus default to the brand blue accent (so the live default no
     * longer depends on the historical migration column default of purple).
     */
    protected $attributes = [
        'accent_color' => '#3d6bff',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
    {
        return $this->hasMany(RestaurantMenuCategory::class, 'menu_id')->orderBy('sort_order')->orderBy('id');
    }

    public function items()
    {
        return $this->hasMany(RestaurantMenuItem::class, 'menu_id')->orderBy('sort_order')->orderBy('id');
    }

    public function tables()
    {
        return $this->hasMany(RestaurantTable::class, 'menu_id')->orderBy('sort_order')->orderBy('id');
    }

    public function orders()
    {
        return $this->hasMany(RestaurantOrder::class, 'menu_id')->latest();
    }

    public function isOrderMode(): bool
    {
        return $this->mode === self::MODE_ORDER;
    }
}
