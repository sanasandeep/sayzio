<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class NfcWrite extends Model
{
    protected $fillable = [
        'user_id', 'link_id', 'written_url', 'tag_uid', 'tag_type',
        'tag_capacity_bytes', 'locked', 'device', 'device_label',
        'platform', 'source', 'written_at', 'lat', 'lng', 'label', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'locked'     => 'boolean',
            'meta'       => 'array',
            'written_at' => 'datetime',
            'lat'        => 'decimal:7',
            'lng'        => 'decimal:7',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }
}
