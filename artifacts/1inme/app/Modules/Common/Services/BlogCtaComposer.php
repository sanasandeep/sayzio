<?php

namespace App\Modules\Common\Services;

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
                $latest = BlogPost::published()->with('category')
                    ->orderByDesc('published_at')->take(3)->get();
            } catch (\Throwable $e) {
                $latest = collect();
            }
        }

        $view->with(['blogCtaEnabled' => $enabled, 'latestCta' => $latest]);
    }
}
