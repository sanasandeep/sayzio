<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class AbVariant extends Model
{
    protected $table = 'ab_variants';

    protected $fillable = [
        'link_id', 'label', 'url', 'weight', 'visitors', 'clicks',
        'sort_order', 'is_winner',
    ];

    protected function casts(): array
    {
        return [
            'weight'    => 'integer',
            'visitors'  => 'integer',
            'clicks'    => 'integer',
            'is_winner' => 'boolean',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }
}
