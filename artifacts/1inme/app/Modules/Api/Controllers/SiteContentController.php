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
            'title'    => $page ? (string) $page->title : 'About Sayzio',
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

    /**
     * Resolved /contact details. Returns the brand's public contact block —
     * address, support email, phone, business hours, social links and map
     * coordinates — exactly the resolution the web
     * {@see \App\Modules\Common\Controllers\SitePageController} performs for the
     * /contact page's "Contact details" card. When the contact SitePage row has
     * an `extra` override it is normalized and served; when no row (or no
     * override) exists, the code defaults
     * ({@see SitePagesContent::contactExtraDefault()}) are returned so the
     * payload always carries the correct brand details (EEFind Private Limited,
     * Banjara Hills, hello@sayzio.app, no phone) rather than stale placeholders.
     * Consumed by both the mobile Contact screen and the marketing site's
     * Contact page so an admin edit flows through without an app rebuild or
     * redeploy.
     */
    public function contact(): JsonResponse
    {
        $page = SitePage::where('slug', 'contact')->first();

        $extra = $page && is_array($page->extra) && !empty($page->extra)
            ? SitePagesContent::normalizeContactExtra($page->extra)
            : SitePagesContent::contactExtraDefault();

        $social = is_array($extra['social'] ?? null) ? $extra['social'] : [];
        $map = is_array($extra['map'] ?? null) ? $extra['map'] : [];

        return $this->ok([
            'title'   => $page ? (string) $page->title : 'Contact us',
            'address' => trim((string) ($extra['address'] ?? '')),
            'email'   => trim((string) ($extra['email'] ?? '')),
            // Blank phone stays blank so the client can omit the row entirely,
            // mirroring the web view's `@if($phone !== '')` guard.
            'phone'   => trim((string) ($extra['phone'] ?? '')),
            'hours'   => trim((string) ($extra['hours'] ?? '')),
            'social'  => [
                'twitter'   => trim((string) ($social['twitter'] ?? '')),
                'instagram' => trim((string) ($social['instagram'] ?? '')),
                'linkedin'  => trim((string) ($social['linkedin'] ?? '')),
                'youtube'   => trim((string) ($social['youtube'] ?? '')),
                'facebook'  => trim((string) ($social['facebook'] ?? '')),
            ],
            'map' => [
                'lat'   => (float) ($map['lat'] ?? 17.3850),
                'lng'   => (float) ($map['lng'] ?? 78.4867),
                'zoom'  => (int) ($map['zoom'] ?? 12),
                'label' => trim((string) ($map['label'] ?? '')),
            ],
        ]);
    }
}
