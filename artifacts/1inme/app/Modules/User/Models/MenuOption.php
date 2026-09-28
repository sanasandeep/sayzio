<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/** One choice inside a MenuOptionGroup. */
class MenuOption extends Model
{
    protected $fillable = [
        'group_id', 'name', 'price_delta', 'sort_order', 'is_sold_out', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_delta' => 'decimal:2',
            'sort_order'  => 'integer',
            'is_sold_out' => 'boolean',
            'is_active'   => 'boolean',
        ];
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
