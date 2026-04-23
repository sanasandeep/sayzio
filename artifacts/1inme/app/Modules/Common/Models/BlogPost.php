<?php

namespace App\Modules\Common\Models;

use App\Modules\Admin\Models\Admin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    protected $fillable = [
        'title', 'slug', 'excerpt', 'body_html', 'cover_image',
        'category_id', 'author_id', 'status',
        'published_at', 'scheduled_at',
        'meta_title', 'meta_description', 'og_image', 'canonical_url',
        'reading_time_min', 'is_featured_home', 'featured_slot',
        'allow_comments', 'view_count',
    ];

    protected $casts = [
        'published_at'      => 'datetime',
        'scheduled_at'      => 'datetime',
        'is_featured_home'  => 'boolean',
        'allow_comments'    => 'boolean',
        'reading_time_min'  => 'integer',
        'view_count'        => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $post) {
            if (empty($post->slug)) {
                $post->slug = static::uniqueSlug($post->title ?: 'post');
            }
            $post->reading_time_min = static::estimateReadingTime((string) $post->body_html);
        });
        // Any time a post is created, updated or deleted, invalidate the
        // public sitemap/RSS caches so changes go live immediately.
        static::saved(fn () => static::flushPublicCaches());
        static::deleted(fn () => static::flushPublicCaches());
    }

    /**
     * Forget cached public listing artifacts (sitemap.xml, rss feed,
     * latest-CTA composer cache, etc.) so the next request rebuilds them.
     */
    public static function flushPublicCaches(): void
    {
        try {
            \Illuminate\Support\Facades\Cache::forget('blog.sitemap.xml');
            \Illuminate\Support\Facades\Cache::forget('blog.rss.xml');
        } catch (\Throwable $e) {
            // Cache flushing must never break the write path.
        }
    }

    public static function uniqueSlug(string $base, ?int $ignoreId = null): string
    {
        $slug = Str::slug($base) ?: 'post';
        $candidate = $slug;
        $i = 2;
        while (static::where('slug', $candidate)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $candidate = $slug . '-' . $i++;
        }
        return $candidate;
    }

    public static function estimateReadingTime(string $html): int
    {
        $text = trim(strip_tags($html));
        if ($text === '') return 1;
        $words = preg_split('/\s+/u', $text);
        $count = is_array($words) ? count($words) : 0;
        return max(1, (int) ceil($count / 200));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'post_id', 'tag_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(BlogComment::class, 'post_id');
    }

    public function approvedComments(): HasMany
    {
        return $this->hasMany(BlogComment::class, 'post_id')->where('status', 'approved');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published')
                 ->where(function ($q) { $q->whereNull('published_at')->orWhere('published_at', '<=', now()); });
    }

    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', 'scheduled');
    }

    public function scopeFeatured(Builder $q): Builder
    {
        return $q->where('is_featured_home', true);
    }

    public function getUrlAttribute(): string
    {
        return route('site.blogs.show', $this->slug);
    }

    /**
     * Build a simple table of contents from h2/h3 headings inside the body.
     * Returns an array of ['id' => ..., 'text' => ..., 'level' => 2|3].
     * Also assigns `id` attributes to headings in the rendered HTML.
     */
    public function tocAndBody(): array
    {
        $html = (string) $this->body_html;
        if ($html === '') return ['toc' => [], 'html' => ''];

        $toc = [];
        $usedIds = [];
        $pattern = '/<h([23])([^>]*)>(.*?)<\/h\1>/is';
        $html = preg_replace_callback($pattern, function ($m) use (&$toc, &$usedIds) {
            $level = (int) $m[1];
            $attrs = $m[2];
            $inner = $m[3];
            $text  = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES, 'UTF-8'));
            if ($text === '') return $m[0];
            $id = Str::slug($text);
            if ($id === '') $id = 'section-' . (count($toc) + 1);
            $base = $id; $i = 2;
            while (in_array($id, $usedIds, true)) { $id = $base . '-' . $i++; }
            $usedIds[] = $id;
            $toc[] = ['id' => $id, 'text' => $text, 'level' => $level];
            // Replace any existing id attribute, then add ours.
            $attrs = preg_replace('/\s+id="[^"]*"/', '', $attrs);
            return '<h' . $level . ' id="' . $id . '"' . $attrs . '>' . $inner . '</h' . $level . '>';
        }, $html);

        return ['toc' => $toc, 'html' => $html];
    }
}
