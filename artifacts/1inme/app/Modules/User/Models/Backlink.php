<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Backlink extends Model
{
    protected $fillable = [
        'user_id', 'page_url', 'page_host', 'page_title', 'anchor_text',
        'matched_url', 'matched_property_type', 'matched_property_value',
        'first_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
