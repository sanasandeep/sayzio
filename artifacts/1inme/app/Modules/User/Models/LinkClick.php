<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class LinkClick extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'link_id', 'alias', 'viewer_user_id', 'block_id', 'block_type', 'destination_url',
        'ip_address', 'country_code', 'city', 'latitude', 'longitude',
        'browser', 'os', 'device_type', 'referrer', 'source',
        'language', 'utm_params', 'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'utm_params' => 'array',
            'clicked_at' => 'datetime',
            'latitude'   => 'float',
            'longitude'  => 'float',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }
}
