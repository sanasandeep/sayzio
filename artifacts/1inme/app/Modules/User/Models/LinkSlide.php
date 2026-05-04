<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkSlide extends Model
{
    protected $fillable = [
        'deck_id', 'sort_order', 'title', 'block_ids', 'background', 'animation', 'transition', 'settings',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'block_ids'  => 'array',
            'background' => 'array',
            'animation'  => 'array',
            'settings'   => 'array',
        ];
    }

    public function deck(): BelongsTo { return $this->belongsTo(LinkSlideDeck::class, 'deck_id'); }
}
