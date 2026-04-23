<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogCategory extends Model
{
    protected $fillable = ['name', 'slug', 'color', 'cover_image', 'description', 'sort_order'];

    protected static function booted(): void
    {
        static::saving(function (self $cat) {
            if (empty($cat->slug)) {
                $cat->slug = static::uniqueSlug($cat->name);
            }
        });
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'category';
        $candidate = $slug;
        $i = 2;
        while (static::where('slug', $candidate)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $candidate = $slug . '-' . $i++;
        }
        return $candidate;
    }

    public function posts()
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }
}
