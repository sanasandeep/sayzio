<?php

namespace App\Modules\Common\Models;

use App\Modules\Common\Support\MarketingSitemap;
use Illuminate\Database\Eloquent\Model;

class SitePage extends Model
{
    protected $fillable = ['slug', 'title', 'meta_description', 'meta_keywords', 'intro', 'last_updated_at', 'show_toc', 'sections', 'extra', 'cta_label', 'cta_url'];

    protected static function booted(): void
    {
        // Any time a marketing page row is created, updated or deleted, drop the
        // cached marketing sitemap (and best-effort ping search engines) so the
        // change is reflected/recrawled promptly.
        static::saved(fn () => MarketingSitemap::flush());
        static::deleted(fn () => MarketingSitemap::flush());
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
