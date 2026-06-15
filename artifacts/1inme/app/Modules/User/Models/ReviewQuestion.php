<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewQuestion extends Model
{
    protected $fillable = [
        'user_id', 'link_id', 'prompt', 'type', 'options',
        'is_required', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options'     => 'array',
            'is_required' => 'boolean',
            'is_active'   => 'boolean',
            'sort_order'  => 'integer',
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

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
