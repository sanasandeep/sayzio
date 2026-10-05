<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantMenuItem extends Model
{
    protected $fillable = [
        'menu_id', 'category_id', 'name', 'description', 'price', 'currency',
        'photo_url', 'marks', 'min_quantity', 'max_quantity', 'coupon_from', 'bulk_price',
        'sort_order', 'is_sold_out', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'       => 'decimal:2',
            'marks'       => 'array',
            // All three are integers or null; Laravel leaves null alone,
            // so a nullable ceiling keeps meaning "no ceiling".
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'coupon_from'  => 'integer',
            'bulk_price' => 'decimal:2',
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

    /**
     * The marks a diner reads on this dish: veg, spicy, no garlic.
     * Resolved rather than returned raw, because a mark retired in admin
     * must stop being drawn on dishes that were saved wearing it.
     */
    public function marksForDisplay(): array
    {
        return \App\Modules\User\Support\MenuItemMarks::resolve($this->marks);
    }
}
