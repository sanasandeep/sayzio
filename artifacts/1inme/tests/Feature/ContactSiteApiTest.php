<?php

namespace Tests\Feature;

use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature coverage for the public GET /api/v1/site/contact endpoint that
 * feeds the marketing site's Contact page. Mirrors the /site/about coverage
 * pattern: assert the endpoint returns the admin override when the contact
 * SitePage row carries one, and the correct brand code-defaults otherwise.
 *
 * This is the server half of the guardrail requested in task #3259: without
 * it, a future edit to SitePagesContent::contactExtraDefault(), the
 * SiteContentController::contact() resolver, or the route could silently ship
 * stale/placeholder contact info (support@1inme.com, a fake phone, the wrong
 * address) to visitors again. The defaults asserted here are the real entity:
 * EEFind Private Limited, Banjara Hills, hello@sayzio.app, and a deliberately
 * blank phone.
 */
class ContactSiteApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_api_returns_code_defaults_when_no_row_exists(): void
    {
        // Ensure there is no contact override row so the controller falls back
        // to the code defaults branch.
        SitePage::where('slug', 'contact')->delete();

        $defaults = SitePagesContent::contactExtraDefault();

        $resp = $this->getJson('/api/v1/site/contact');
        $resp->assertOk();

        // The correct brand contact details — never stale placeholders.
        $resp->assertJsonPath('data.email', 'hello@sayzio.app');
        $resp->assertJsonPath('data.phone', '');
        $resp->assertJsonPath('data.address', $defaults['address']);
        $this->assertStringContainsString('Banjara Hills', $resp->json('data.address'));
        $this->assertStringContainsString('EEFind Private Limited', $resp->json('data.address'));

        // A fake phone must never leak back in.
        $this->assertSame('', $resp->json('data.phone'));

        // Map + social sub-shapes are present so the marketing client can rely
        // on them without null-guarding every key.
        $resp->assertJsonStructure([
            'data' => [
                'title', 'address', 'email', 'phone', 'hours',
                'social' => ['twitter', 'instagram', 'linkedin', 'youtube', 'facebook'],
                'map' => ['lat', 'lng', 'zoom', 'label'],
            ],
        ]);
    }

    public function test_contact_api_returns_admin_override_when_row_has_one(): void
    {
        $override = SitePagesContent::normalizeContactExtra([
            'address' => "Override Tower\n99 Editable Street, Hyderabad",
            'email'   => 'override@sayzio.app',
            'phone'   => '+91 40 5555 0000',
            'hours'   => 'Mon–Sat · 09:00 – 21:00 IST',
            'social'  => [
                'twitter'   => 'https://x.com/overridden',
                'instagram' => 'https://instagram.com/overridden',
                'linkedin'  => '',
                'youtube'   => '',
                'facebook'  => '',
            ],
            'map' => ['lat' => 12.9716, 'lng' => 77.5946, 'zoom' => 12, 'label' => 'Override office'],
        ]);

        $page = SitePage::firstOrNew(['slug' => 'contact']);
        $page->fill([
            'title'            => 'Contact us — Edited',
            'meta_description' => 'Edited contact page.',
            'sections'         => SitePagesContent::contactSectionsDefault(),
            'extra'            => $override,
        ])->save();

        $resp = $this->getJson('/api/v1/site/contact');
        $resp->assertOk();

        // The admin-edited values are served verbatim — not the defaults.
        $resp->assertJsonPath('data.title', 'Contact us — Edited');
        $resp->assertJsonPath('data.email', 'override@sayzio.app');
        $resp->assertJsonPath('data.phone', '+91 40 5555 0000');
        $resp->assertJsonPath('data.address', "Override Tower\n99 Editable Street, Hyderabad");
        $resp->assertJsonPath('data.hours', 'Mon–Sat · 09:00 – 21:00 IST');
        $resp->assertJsonPath('data.social.twitter', 'https://x.com/overridden');
        $resp->assertJsonPath('data.map.label', 'Override office');
        $resp->assertJsonPath('data.map.zoom', 12);

        // And crucially, the default brand values are NOT what was returned.
        $this->assertNotSame('hello@sayzio.app', $resp->json('data.email'));
    }
}
