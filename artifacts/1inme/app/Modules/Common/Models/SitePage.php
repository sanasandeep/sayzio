<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

class SitePage extends Model
{
    protected $fillable = ['slug', 'title', 'meta_description', 'sections', 'cta_label', 'cta_url'];

    protected function casts(): array
    {
        return ['sections' => 'array'];
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
