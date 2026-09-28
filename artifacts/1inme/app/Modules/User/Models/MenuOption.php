<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/** One choice inside a MenuOptionGroup. */
class MenuOption extends Model
{
    protected $fillable = [
        'group_id', 'name', 'icon', 'icon_repeat', 'price_delta', 'sort_order', 'is_sold_out', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'sort_order'  => 'integer',
            'icon_repeat' => 'integer',
            'is_sold_out' => 'boolean',
            'is_active'   => 'boolean',
        ];
    }

    /** A catalogue key we can draw, or null. Never whatever is in the row. */
    public function iconKey(): ?string
    {
        return \App\Modules\User\Support\MenuOptionIcon::sanitize($this->icon);
    }

    /** How many times to draw it: 1 when there is no icon. */
    public function iconRepeat(): int
    {
        return \App\Modules\User\Support\MenuOptionIcon::repeat($this->icon, $this->icon_repeat);
    }

    public function group()
    {
        return $this->belongsTo(MenuOptionGroup::class, 'group_id');
    }

    /** Whether a guest can pick this right now. */
    public function isAvailable(): bool
    {
        return $this->is_active && ! $this->is_sold_out;
    }
}
