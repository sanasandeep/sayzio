<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\MarketingSeo;
use Illuminate\Http\Request;

/**
 * Unified "Marketing SEO" admin screen: one place to manage the meta title,
 * description and keywords of every public marketing page.
 *
 *  - Code-driven pages (home, pricing, the comparison pages, capability
 *    pages, the creators directory, …) are edited inline here; overrides are
 *    persisted to the central {@see MarketingSeo::SETTING_KEY} AppSetting map.
 *    Leaving a field blank falls the page back to its seeded default.
 *  - Content pages backed by a `site_pages` row deep-link to the existing
 *    Site Pages editor (which now also carries the keywords field), so there
 *    is a single, consistent editing surface for those.
 */
class MarketingSeoController extends Controller
{
    public function index()
    {
        $defaults = MarketingSeo::codeDrivenDefaults();
        $overrides = MarketingSeo::overrides();

        // Group the code-driven pages for display, preserving registry order.
        $codeGroups = [];
        foreach ($defaults as $key => $def) {
            $ov = is_array($overrides[$key] ?? null) ? $overrides[$key] : [];
            $codeGroups[$def['group']][] = [
                'key'         => $key,
                'label'       => $def['label'],
                'url'         => $def['url'],
                'default'     => [
                    'title'       => $def['title'] ?? '',
                    'description' => $def['description'] ?? '',
                    'keywords'    => $def['keywords'] ?? '',
                ],
                'override'    => [
                    'title'       => (string) ($ov['title'] ?? ''),
                    'description' => (string) ($ov['description'] ?? ''),
                    'keywords'    => (string) ($ov['keywords'] ?? ''),
                ],
            ];
        }

        // Group the site-page-backed pages (deep-link to the existing editor).
        $rows = SitePage::whereIn('slug', MarketingSeo::sitePageSlugs())
            ->get(['slug', 'title', 'meta_description', 'meta_keywords'])
            ->keyBy('slug');
        $keywordDefaults = MarketingSeo::sitePageKeywordDefaults();

        $siteGroups = [];
        foreach (MarketingSeo::sitePageLabels() as $slug => $meta) {
            $row = $rows->get($slug);
            $siteGroups[$meta['group']][] = [
                'slug'             => $slug,
                'label'            => $meta['label'],
                'exists'           => $row !== null,
                'title'            => (string) ($row->title ?? ''),
                'meta_description' => (string) ($row->meta_description ?? ''),
                'meta_keywords'    => (string) ($row->meta_keywords ?? ($keywordDefaults[$slug] ?? '')),
                'edit_url'         => route('admin.site-pages.edit', $slug),
            ];
        }

        return view('admin.marketing-seo.index', [
            'codeGroups' => $codeGroups,
            'siteGroups' => $siteGroups,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'seo'               => 'nullable|array',
            'seo.*.title'       => 'nullable|string|max:200',
            'seo.*.description' => 'nullable|string|max:500',
            'seo.*.keywords'    => 'nullable|string|max:500',
        ]);

        $allowed = array_keys(MarketingSeo::codeDrivenDefaults());
        $submitted = is_array($data['seo'] ?? null) ? $data['seo'] : [];

        $map = [];
        foreach ($submitted as $key => $fields) {
            if (!in_array($key, $allowed, true) || !is_array($fields)) {
                continue;
            }
            $entry = [];
            foreach (['title', 'description', 'keywords'] as $field) {
                $value = trim((string) ($fields[$field] ?? ''));
                if ($value !== '') {
                    // Only persist a non-empty value as an override. Leaving a
                    // field blank means "use the seeded default", so we drop it.
                    $entry[$field] = $value;
                }
            }
            if (!empty($entry)) {
                $map[$key] = $entry;
            }
        }

        AppSetting::put(MarketingSeo::SETTING_KEY, $map);

        // The marketing sitemap + sitemap index embed these overrides (and the
        // marketing_seo row's timestamp drives <lastmod>), so drop both caches
        // and best-effort ping search engines about the change.
        \App\Modules\Common\Controllers\SitemapController::flushPublicCaches();

        return back()->with('success', 'Marketing SEO saved.');
    }
}
