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
        return \App\Support\UniqueSuffix::resolve(
            static::query()->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId)),
            $slug
        );
    }

    public function posts()
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }
}
