<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed event category (Task #3654). Powers the icon/label/color
 * lookups used to be hardcoded in `App\Modules\User\Support\EventCategories`
 * and the browse-by-category row on `/events`.
 */
class EventCategory extends Model
{
    protected $fillable = [
        'slug', 'name', 'icon', 'color_from', 'color_to', 'sort_order', 'is_enabled',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
