<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class RoadmapComment extends Model
{
    protected $fillable = [
        'item_id', 'viewer_user_id', 'user_id', 'author_name', 'body',
        'is_creator', 'is_hidden', 'fingerprint', 'ip',
    ];

    protected function casts(): array
    {
        return [
            'is_creator' => 'boolean',
            'is_hidden'  => 'boolean',
        ];
    }

    public function item() { return $this->belongsTo(RoadmapItem::class, 'item_id'); }
}
