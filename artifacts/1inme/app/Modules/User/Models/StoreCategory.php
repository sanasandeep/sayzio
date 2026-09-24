<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class StoreCategory extends Model
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
        return $this->belongsTo(StoreMenu::class, 'menu_id');
    }

    public function products()
    {
        return $this->hasMany(StoreProduct::class, 'category_id')->orderBy('sort_order')->orderBy('id');
    }

    /** The section this one sits inside, or null when it IS a section. */
    public function parent()
    {
        return $this->belongsTo(StoreCategory::class, 'parent_id');
    }

    /** Sub-sections, one level only. See MenuTree::MAX_DEPTH. */
    public function children()
    {
        return $this->hasMany(StoreCategory::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }
}
