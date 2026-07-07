<?php

namespace App\Modules\Common\Services;

use App\Modules\Common\Models\BlogCategory;
use App\Modules\Common\Models\BlogPost;
use Illuminate\View\View;

/**
 * View composer for the "Latest from blog" marketing block.
 *
 * Reads the per-page list of opt-in marketing slugs from BlogSettings
 * and, when the current view's slug is enabled, loads the three latest
 * published posts. The partial itself is presentation-only.
 */
class BlogCtaComposer
{
    /** @var array<string,string> view name -> page slug used in settings */
    public const VIEW_TO_SLUG = [
        'public.features'      => 'features',
        'public.about'         => 'about',
        'public.how-it-works'  => 'how-it-works',
        'public.contact'       => 'contact',
        'public.faqs'          => 'faqs',
    ];

    /**
     * The three latest published posts are cached for 5 minutes as PLAIN
     * ATTRIBUTE ARRAYS (post + category) and rehydrated on read, so warm
     * marketing pages don't pay a per-request blog query (production runs
     * DB_PERSISTENT=false — each query re-opens a ~3s SSL connection).
     * Never cache Eloquent models in the file cache (__PHP_Incomplete_Class).
     * Flushed by {@see BlogPost::flushPublicCaches()} whenever a post changes.
     */
    public const CACHE_KEY = 'blog:latest_cta:v1';

    /**
     * Build the cacheable payload (plain attribute arrays). Shared by the
     * request-path Cache::remember below and MarketingPageCache::warm().
     */
    public static function buildRows(): array
    {
        return BlogPost::published()->with('category')
            ->orderByDesc('published_at')->take(3)->get()
            ->map(fn (BlogPost $p) => [
                'post'     => $p->getAttributes(),
                'category' => $p->category?->getAttributes(),
            ])
            ->all();
    }

    public function compose(View $view): void
    {
        $slug = self::VIEW_TO_SLUG[$view->getName()] ?? null;
        if (!$slug) {
            $view->with(['blogCtaEnabled' => false, 'latestCta' => collect()]);
            return;
        }

        try {
            $settings = BlogSettings::all();
        } catch (\Throwable $e) {
            $settings = [];
        }
        $enabledPages = (array) ($settings['cta_on_pages'] ?? []);
        $enabled = in_array($slug, $enabledPages, true);

        $latest = collect();
        if ($enabled) {
            try {
                $rows = \Illuminate\Support\Facades\Cache::remember(self::CACHE_KEY, 300, fn () => self::buildRows());
                $latest = collect($rows)->map(function (array $row) {
                    $post = BlogPost::query()->hydrate([$row['post']])->first();
                    $post->setRelation('category', !empty($row['category'])
                        ? BlogCategory::query()->hydrate([$row['category']])->first()
                        : null);
                    return $post;
                });
            } catch (\Throwable $e) {
                $latest = collect();
            }
        }

        $view->with(['blogCtaEnabled' => $enabled, 'latestCta' => $latest]);
    }
}
