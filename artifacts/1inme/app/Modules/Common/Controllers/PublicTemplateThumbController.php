<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\User\Support\TemplateThumbnailRenderer;

/**
 * Public, cache-friendly SVG preview thumbnails for Page Templates.
 *
 * `GET /template-thumbs/{slug}.svg` renders a theme-aware preview card
 * straight from the template's stored snapshot, so seeded templates get
 * a visually distinct, self-hosted thumbnail without any external photo
 * CDN or screenshot pipeline. Seeders point `thumbnail_url` here with a
 * `?v=SEED_VERSION` cache-buster, so blueprint redesigns automatically
 * re-render every card.
 */
class PublicTemplateThumbController extends Controller
{
    public function show(string $slug)
    {
        $tpl = PageTemplate::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first(['id', 'slug', 'snapshot']);

        abort_if(!$tpl, 404);

        $svg = TemplateThumbnailRenderer::render((array) ($tpl->snapshot ?? []), $slug);

        return response($svg, 200, [
            'Content-Type'  => 'image/svg+xml',
            // Long-lived: the seeder's ?v=SEED_VERSION query busts on redesign.
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
