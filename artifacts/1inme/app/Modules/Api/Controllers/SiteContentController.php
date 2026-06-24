<?php

namespace App\Modules\Api\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Http\JsonResponse;

/**
 * Public, no-login marketing content endpoints for the mobile app. These
 * mirror the admin-editable copy served on the web marketing pages so the
 * mobile "Info" screens (e.g. the About → EEFind parent-company block) stay
 * in sync automatically: when an admin edits the web /about page, the same
 * content flows to mobile without an app rebuild.
 */
class SiteContentController extends Controller
{
    use ApiResponses;

    /**
     * Resolved /about content. Returns the page title, the editable narrative
     * sections, and the normalized parent-company ("EEFind") block — exactly
     * the resolution the web {@see \App\Modules\Common\Controllers\SitePageController}
     * performs. When no DB row exists yet, code defaults are returned so the
     * payload is always renderable.
     */
    public function about(): JsonResponse
    {
        $page = SitePage::where('slug', 'about')->first();

        $extra = $page && is_array($page->extra) && !empty($page->extra)
            ? SitePagesContent::normalizeAboutExtra($page->extra)
            : SitePagesContent::aboutExtraDefault();

        $sections = $page && is_array($page->sections) && !empty($page->sections)
            ? $page->visibleSections()
            : SitePagesContent::aboutSectionsDefault();

        // Normalize the narrative sections to a simple {heading, body} shape
        // the mobile InfoPage consumes directly.
        $cleanSections = [];
        foreach ($sections as $s) {
            if (!is_array($s)) continue;
            $heading = trim((string) ($s['heading'] ?? ''));
            $body = trim((string) ($s['body'] ?? ''));
            if ($body === '' && $heading === '') continue;
            $cleanSections[] = ['heading' => $heading, 'body' => $body];
        }

        $ee = $extra['eefind'] ?? SitePagesContent::aboutEefindDefault();

        return $this->ok([
            'title'    => $page ? (string) $page->title : 'About 1INME',
            'sections' => $cleanSections,
            'eefind'   => [
                'eyebrow'     => (string) ($ee['eyebrow'] ?? ''),
                'heading'     => (string) ($ee['heading'] ?? ''),
                'body'        => (string) ($ee['body'] ?? ''),
                'stats'       => array_values(array_map(static function ($row) {
                    $row = is_array($row) ? $row : [];
                    return [
                        'value'  => (string) ($row['value'] ?? ''),
                        'suffix' => (string) ($row['suffix'] ?? ''),
                        'label'  => (string) ($row['label'] ?? ''),
                    ];
                }, (array) ($ee['stats'] ?? []))),
                'address'     => (string) ($ee['address'] ?? ''),
                'email'       => (string) ($ee['email'] ?? ''),
                'whatsapp'    => (string) ($ee['whatsapp'] ?? ''),
                'website'     => (string) ($ee['website'] ?? ''),
                'website_url' => (string) ($ee['website_url'] ?? ''),
            ],
        ]);
    }
}
