<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BlogTag extends Model
{
    protected $fillable = ['name', 'slug'];

    protected static function booted(): void
    {
        static::saving(function (self $t) {
            if (empty($t->slug)) {
                $t->slug = static::uniqueSlug($t->name);
            }
        });
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'tag';
        return \App\Support\UniqueSuffix::resolve(
            static::query()->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId)),
            $slug
        );
    }

    public function posts()
    {
        return $this->belongsToMany(BlogPost::class, 'blog_post_tag', 'tag_id', 'post_id');
    }
}
