<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'quote', 'author_name', 'author_role', 'accent_color',
        'rating', 'row', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'rating'     => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    public function scopeOrdered($q)
    {
        return $q->orderBy('sort_order')->orderBy('id');
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->author_name), 0, 1));
    }
}
