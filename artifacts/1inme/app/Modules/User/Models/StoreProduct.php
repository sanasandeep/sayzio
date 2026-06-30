<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class StoreProduct extends Model
{
    protected $fillable = [
        'menu_id', 'category_id', 'name', 'description', 'price', 'currency',
        'photo_url', 'sort_order', 'is_out_of_stock', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price'           => 'decimal:2',
            'is_out_of_stock' => 'boolean',
            'is_active'       => 'boolean',
        ];
    }

    public function menu()
    {
        return $this->belongsTo(StoreMenu::class, 'menu_id');
    }

    public function category()
    {
        return $this->belongsTo(StoreCategory::class, 'category_id');
    }
}
