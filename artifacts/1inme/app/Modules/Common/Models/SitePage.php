<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

class SitePage extends Model
{
    protected $fillable = ['slug', 'title', 'meta_description', 'meta_keywords', 'intro', 'last_updated_at', 'show_toc', 'sections', 'extra', 'cta_label', 'cta_url'];

    /**
     * Prefix for the per-slug attribute-array cache used by
     * {@see cachedBySlug()}. Bump the version to invalidate all entries.
     */
    public const SLUG_CACHE_PREFIX = 'sitepage:attrs:v1:';

    protected static function booted(): void
    {
        // Any time a marketing page is created, updated or deleted, invalidate
        // the cached marketing sitemap + sitemap index (and best-effort ping
        // search engines) so changes go live and are recrawled promptly.
        // Also drop the page's own per-slug attribute cache so admin edits
        // to marketing copy go live immediately.
        static::saved(function (self $page) {
            \App\Modules\Common\Controllers\SitemapController::flushPublicCaches();
            $page->forgetSlugCache();
        });
        static::deleted(function (self $page) {
            \App\Modules\Common\Controllers\SitemapController::flushPublicCaches();
            $page->forgetSlugCache();
        });
    }

    protected function forgetSlugCache(): void
    {
        try {
            \Illuminate\Support\Facades\Cache::forget(self::SLUG_CACHE_PREFIX . $this->slug);
            // The slug itself may have been renamed — drop the old key too.
            if ($this->isDirty('slug') && $this->getOriginal('slug')) {
                \Illuminate\Support\Facades\Cache::forget(self::SLUG_CACHE_PREFIX . $this->getOriginal('slug'));
            }
        } catch (\Throwable $e) {
            // Cache flushing must never break the write path.
        }
    }

    /**
     * Cached lookup for hot public marketing pages: stores the row as a
     * PLAIN ATTRIBUTE ARRAY (never a serialized model — the file cache
     * turns those into __PHP_Incomplete_Class) and rehydrates on read, so
     * warm anonymous requests skip the per-request query (production runs
     * DB_PERSISTENT=false, making each query a ~3s SSL reconnect).
     * Only positive hits are cached; a missing row always re-queries so
     * firstOrCreate/firstOrFail call-sites keep their semantics.
     */
    public static function cachedBySlug(string $slug): ?self
    {
        try {
            $attrs = \Illuminate\Support\Facades\Cache::remember(
                self::SLUG_CACHE_PREFIX . $slug,
                300,
                function () use ($slug) {
                    $row = static::where('slug', $slug)->first();
                    // Cache::remember treats null as a miss, so a missing
                    // row is simply never cached (and re-queried next time).
                    return $row?->getAttributes();
                }
            );
        } catch (\Throwable $e) {
            $attrs = null;
        }

        if (!is_array($attrs) || $attrs === []) {
            return null;
        }

        return static::query()->hydrate([$attrs])->first();
    }

    protected function casts(): array
    {
        return [
            'sections'        => 'array',
            'extra'           => 'array',
            'last_updated_at' => 'date',
            'show_toc'        => 'boolean',
        ];
    }

    /**
     * Sections that should be rendered on the public page (visible only).
     */
    public function visibleSections(): array
    {
        $sections = is_array($this->sections) ? $this->sections : [];
        return array_values(array_filter($sections, function ($s) {
            return !array_key_exists('visible', $s) || (bool) $s['visible'];
        }));
    }

    public function faqs()
    {
        return FaqItem::where('page_slug', $this->slug)->orderBy('sort_order')->orderBy('id')->get();
    }

    /**
     * Load an error page by slug, returning a safe defaults object when the
     * seed row is missing so the site never white-screens during a failure.
     */
    public static function resolveErrorPage(string $slug): object
    {
        $defaults = [
            'error-404' => [
                'title'            => 'Page not found',
                'meta_description' => 'The page you were looking for does not exist or has been moved.',
                'sections'         => [[
                    'heading' => "We can't find that page",
                    'body'    => "The link you followed may be broken, or the page may have been removed.",
                ]],
                'cta_label' => 'Back to home',
                'cta_url'   => '/',
            ],
            'error-403' => [
                'title'            => 'No access',
                'meta_description' => "You don't have permission to view this page.",
                'sections'         => [[
                    'heading' => "You don't have access to this page",
                    'body'    => "You may need to sign in with a different account, or ask the page owner for permission.",
                ]],
                'cta_label' => 'Back to home',
                'cta_url'   => '/',
            ],
            'error-405' => [
                'title'            => "That isn't a valid page",
                'meta_description' => "This link can't be opened the way you reached it. Head back to where you started.",
                'sections'         => [[
                    'heading' => "This isn't a valid page",
                    'body'    => "The link you followed can't be opened directly. This usually happens when a link is mistyped or opened out of order. Use the button below to get back on track.",
                ]],
                'cta_label' => 'Back to login',
                'cta_url'   => '/login',
            ],
            'error-500' => [
                'title'            => 'Something went wrong',
                'meta_description' => 'An unexpected error occurred on our side. Please try again in a moment.',
                'sections'         => [[
                    'heading' => 'We hit a snag',
                    'body'    => "Sorry — something went wrong on our end. Our team has been notified. Please try again in a few minutes.",
                ]],
                'cta_label' => 'Back to home',
                'cta_url'   => '/',
            ],
            'error-503' => [
                'title'            => "We'll be right back",
                'meta_description' => 'The site is temporarily down for maintenance.',
                'sections'         => [[
                    'heading' => 'Down for maintenance',
                    'body'    => "We're making some quick improvements and will be back online shortly. Thanks for your patience.",
                ]],
                'cta_label' => 'Check our status',
                'cta_url'   => '/',
            ],
            'error-419' => [
                'title'            => 'Your session expired',
                'meta_description' => 'For your security, your session timed out. Please refresh and try again.',
                'sections'         => [[
                    'heading' => 'Session expired',
                    'body'    => "For your security, this page has been open too long. Please go back, refresh, and try again.",
                ]],
                'cta_label' => 'Back to home',
                'cta_url'   => '/',
            ],
            'error-429' => [
                'title'            => 'Too many requests',
                'meta_description' => "You've sent too many requests in a short time. Please slow down and try again.",
                'sections'         => [[
                    'heading' => 'Slow down a moment',
                    'body'    => "You've made a lot of requests very quickly. Please wait a few seconds and try again.",
                ]],
                'cta_label' => 'Back to home',
                'cta_url'   => '/',
            ],
        ];

        $fallback = $defaults[$slug] ?? $defaults['error-404'];
        $record   = static::where('slug', $slug)->first();

        return (object) [
            'slug'             => $slug,
            'title'            => $record->title ?? $fallback['title'],
            'meta_description' => $record->meta_description ?? $fallback['meta_description'],
            'sections'         => $record->sections ?? $fallback['sections'],
            'cta_label'        => $record->cta_label ?? $fallback['cta_label'],
            'cta_url'          => $record->cta_url ?? $fallback['cta_url'],
        ];
    }
}
