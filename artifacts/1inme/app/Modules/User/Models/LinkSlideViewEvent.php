<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class LinkSlideViewEvent extends Model
{
    public $timestamps = false;
    protected $table = 'link_slide_view_events';

    protected $fillable = [
        'deck_id', 'link_id', 'slide_index', 'completed',
        'page_session_id', 'source', 'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'slide_index' => 'integer',
            'completed'   => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }
}
