<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class LinkPerformanceSnapshot extends Model
{
    protected $fillable = [
        'link_id', 'date', 'score', 'components_json',
    ];

    protected function casts(): array
    {
        return [
            'date'            => 'date',
            'score'           => 'integer',
            'components_json' => 'array',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }
}
