<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\SiteStat;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Http\Request;

/**
 * Preview routes for the "schematic" marketing art direction.
 *
 * These render the redesigned home / features / pricing / about pages from
 * exactly the same data the live pages use, under a separate URL prefix, so
 * the new design can be reviewed on production without touching a single
 * page a visitor can reach. Nothing here writes; every route is noindex.
 *
 * The pricing page deliberately delegates to PricingPagesController so the
 * currency, cycle, tax and coin-package logic cannot drift between the
 * preview and the real page: same data, different template.
 */
class SchematicPreviewController extends Controller
{
    /** The features page's stored sections, or the built-in defaults. */
    private function featuresCategories(): array
    {
        $page = SitePage::cachedBySlug('features');
        $sections = ($page && is_array($page->sections)) ? $page->sections : [];

        $categories = SitePagesContent::normalizeFeaturesCategories($sections);

        return empty($categories) ? SitePagesContent::featuresCategoriesDefault() : $categories;
    }

    /** The 18 link types, normalised to {name, icon, description}. */
    private function linkTypes(): array
    {
        $page = SitePage::cachedBySlug('features');
        $sections = ($page && is_array($page->sections)) ? $page->sections : [];

        return SitePagesContent::featuresLinkTypesFromSections($sections);
    }

    /**
     * Split the catalogue into the one type that leads the section and the
     * rest, which become the index. Link in Bio leads because it is what
     * most visitors arrive looking for; if it is ever renamed or removed,
     * the first row leads instead.
     */
    private function splitTypes(array $types): array
    {
        $featuredIndex = null;
        foreach ($types as $i => $type) {
            if (strtolower((string) ($type['name'] ?? '')) === 'link in bio') {
                $featuredIndex = $i;
                break;
            }
        }
        if ($featuredIndex === null) {
            $featuredIndex = 0;
        }

        $featured = $types[$featuredIndex] ?? ['name' => 'Link in Bio', 'description' => ''];
        unset($types[$featuredIndex]);

        $rest = array_values(array_map(fn ($t) => [
            'name' => (string) ($t['name'] ?? ''),
            'desc' => (string) ($t['description'] ?? ''),
        ], $types));

        return [$featured, $rest];
    }

    private function stats()
    {
        try {
            return SiteStat::cachedActive();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function home()
    {
        $stats = $this->stats();
        [$featured, $rest] = $this->splitTypes($this->linkTypes());

        $tail = $stats->slice(1);
        $statsMax = 0.0;
        foreach ($tail as $stat) {
            $target = $stat->numericTarget();
            if ($target !== null && $target > $statsMax) {
                $statsMax = $target;
            }
        }

        $first = $stats->first();
        $heroProof = $first
            ? trim($first->value . $first->suffix . ' ' . $first->label) . ' · Free forever · No card'
            : 'Free forever · No card required';

        return view('public.schematic.home', [
            'seoKey'     => 'home',
            'stats'      => $stats,
            'statsMax'   => $statsMax,
            'heroProof'  => $heroProof,
            'featured'   => $featured,
            'types'      => $rest,
            'typeCount'  => count($rest) + 1,
        ]);
    }

    public function features()
    {
        [$featured, $rest] = $this->splitTypes($this->linkTypes());

        return view('public.schematic.features', [
            'seoKey'     => 'features',
            'categories' => $this->featuresCategories(),
            'featured'   => $featured,
            'types'      => $rest,
            'typeCount'  => count($rest) + 1,
            'stats'      => $this->stats(),
        ]);
    }

    public function pricing(Request $request)
    {
        $delegated = app(PricingPagesController::class)->plans($request);

        $data = method_exists($delegated, 'getData') ? $delegated->getData() : [];
        $data['seoKey'] = 'pricing';

        return view('public.schematic.pricing', $data);
    }

    public function about()
    {
        $page = SitePage::cachedBySlug('about');
        $extra = ($page && is_array($page->extra) && !empty($page->extra))
            ? SitePagesContent::normalizeAboutExtra($page->extra)
            : SitePagesContent::aboutExtraDefault();

        return view('public.schematic.about', [
            'seoKey' => 'about',
            'page'   => $page,
            'extra'  => $extra,
            'stats'  => $this->stats(),
        ]);
    }
}
