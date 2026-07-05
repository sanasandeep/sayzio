<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed predefined hashtag (Task #3654) for the `/events` directory
 * hashtag row. Listed ahead of the auto-computed trending tags.
 */
class EventHashtag extends Model
{
    protected $fillable = ['tag', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** Normalize to a bare, lowercase, hash-free tag string. */
    public static function normalize(string $tag): string
    {
        return mb_strtolower(ltrim(trim($tag), '#'));
    }
}
