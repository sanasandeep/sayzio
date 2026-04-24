<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\SitePage;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature coverage for the /contact page editor (admin). The Contact
 * editor is small but its `extra` payload is structurally tricky:
 * lat/long must be clamped to valid ranges, zoom must be clamped to
 * 1–19, blank text fields must collapse to empty strings rather than
 * the seeded Hyderabad defaults, and the social link sub-array must
 * round-trip per platform. These tests guard against a future schema
 * change silently dropping any of those guarantees.
 */
class ContactSitePageEditorTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
        return Admin::create([
            'name'     => 'Test Admin',
            'email'    => 'admin' . uniqid() . '@example.com',
            'password' => Hash::make('secret'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    private function makeContactPage(): SitePage
    {
        // The Contact page is seeded by an earlier migration — refresh
        // its content here so each test starts from a known shape.
        $page = SitePage::firstOrNew(['slug' => 'contact']);
        $page->fill([
            'title'            => 'Contact us',
            'meta_description' => 'Seeded contact page.',
            'sections'         => SitePagesContent::contactSectionsDefault(),
            'extra'            => SitePagesContent::contactExtraDefault(),
        ])->save();
        return $page;
    }

    /**
     * Minimal-but-valid Contact update payload. Each test overrides the
     * `extra` block with the values it needs to exercise.
     */
    private function payload(array $extra): array
    {
        return [
            'title'            => 'Contact us',
            'meta_description' => 'Updated contact description.',
            'sections'         => [
                ['id' => '', 'heading' => 'We love hearing from you', 'body' => 'Drop us a note.', 'visible' => '1'],
            ],
            'cta_label' => '',
            'cta_url'   => '',
            'extra'     => $extra,
        ];
    }

    public function test_admin_save_round_trips_full_contact_extra(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeContactPage();

        $payload = $this->payload([
            'address' => "  1INME HQ\n42 Example Street  ", // outer whitespace must be trimmed
            'email'   => '  hello@example.com  ',
            'phone'   => '  +91 40 9876 5432  ',
            'hours'   => "Mon–Fri · 09:00 – 17:00",
            'social'  => [
                'twitter'   => 'https://twitter.com/onein',
                'instagram' => 'https://instagram.com/onein',
                'linkedin'  => 'https://linkedin.com/company/onein',
                'youtube'   => 'https://youtube.com/@onein',
                'facebook'  => 'https://facebook.com/onein',
            ],
            'map' => [
                'lat'   => 12.9716,
                'lng'   => 77.5946,
                'zoom'  => 12,
                'label' => '  Bengaluru office  ',
            ],
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $payload);

        $resp->assertRedirect(route('admin.site-pages.edit', 'contact'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertIsArray($page->extra);

        // The persisted `extra` should equal what normalizeContactExtra
        // produces for the same input — i.e. a true round-trip. The
        // controller may add a `blog_block` sub-key from a separate
        // input — strip it here so we compare the Contact-specific shape.
        $expected = SitePagesContent::normalizeContactExtra($payload['extra']);
        $stored = $page->extra;
        unset($stored['blog_block']);
        $this->assertEquals($expected, $stored);

        // Specific guarantees: trims wrapping whitespace on text fields
        // and on the map label, but preserves embedded newlines so
        // multi-line addresses render correctly on the public page.
        $this->assertSame("1INME HQ\n42 Example Street", $stored['address']);
        $this->assertSame('hello@example.com',           $stored['email']);
        $this->assertSame('+91 40 9876 5432',            $stored['phone']);
        $this->assertSame('Bengaluru office',            $stored['map']['label']);

        // Every supported social platform round-trips per platform —
        // a future refactor that drops one (e.g. by hard-coding a
        // shorter whitelist) would fail here.
        $this->assertSame([
            'twitter'   => 'https://twitter.com/onein',
            'instagram' => 'https://instagram.com/onein',
            'linkedin'  => 'https://linkedin.com/company/onein',
            'youtube'   => 'https://youtube.com/@onein',
            'facebook'  => 'https://facebook.com/onein',
        ], $stored['social']);

        // Map coordinates and zoom inside their valid windows survive
        // unchanged (no accidental clamp).
        $this->assertSame(12.9716, $stored['map']['lat']);
        $this->assertSame(77.5946, $stored['map']['lng']);
        $this->assertSame(12,      $stored['map']['zoom']);
    }

    public function test_blank_contact_text_fields_collapse_to_empty_strings(): void
    {
        // Even though the Contact page has seeded defaults (Hyderabad
        // address/email/phone/hours), clearing those fields in the
        // editor must persist as empty strings — not silently restore
        // the seed values. Otherwise an admin can never empty a field.
        $admin = $this->makeAdmin();
        $page  = $this->makeContactPage();

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => '   ',  // whitespace-only collapses to ''
                'email'   => '',
                'phone'   => '',
                'hours'   => '',
                'social'  => [
                    'twitter'   => '',
                    'instagram' => '',
                    'linkedin'  => '',
                    'youtube'   => '',
                    'facebook'  => '',
                ],
                'map' => [
                    'lat'   => 0,
                    'lng'   => 0,
                    'zoom'  => 5,
                    'label' => '',
                ],
            ]));

        $resp->assertRedirect(route('admin.site-pages.edit', 'contact'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $stored = $page->extra;
        $this->assertSame('', $stored['address']);
        $this->assertSame('', $stored['email']);
        $this->assertSame('', $stored['phone']);
        $this->assertSame('', $stored['hours']);
        $this->assertSame('', $stored['map']['label']);
        // Social links collapse per platform — none should fall back
        // to the seeded URL.
        foreach (['twitter', 'instagram', 'linkedin', 'youtube', 'facebook'] as $k) {
            $this->assertSame('', $stored['social'][$k], "social.$k should collapse to empty string");
        }
    }

    public function test_out_of_range_lat_long_and_zoom_get_clamped(): void
    {
        // The controller's `between:` validation rejects flagrantly
        // bad numerics, so this test exercises the normaliser layer
        // directly: even if a future refactor loosens the controller
        // rule, normalizeContactExtra itself must clamp lat/long/zoom
        // so the public page never receives invalid coordinates.
        $clampedHigh = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => 999.0,  'lng' => 999.0,  'zoom' => 99],
        ]);
        $this->assertSame(90.0,  $clampedHigh['map']['lat']);
        $this->assertSame(180.0, $clampedHigh['map']['lng']);
        $this->assertSame(19,    $clampedHigh['map']['zoom']);

        $clampedLow = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => -999.0, 'lng' => -999.0, 'zoom' => 0],
        ]);
        $this->assertSame(-90.0,  $clampedLow['map']['lat']);
        $this->assertSame(-180.0, $clampedLow['map']['lng']);
        $this->assertSame(1,      $clampedLow['map']['zoom']);

        // Non-numeric coordinates fall back to the seeded defaults
        // rather than silently becoming 0/0 (which would render an
        // empty ocean tile on the public map).
        $defaults = SitePagesContent::contactExtraDefault();
        $missing = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => 'not-a-number', 'lng' => null, 'zoom' => 'nope'],
        ]);
        $this->assertSame((float) $defaults['map']['lat'],  $missing['map']['lat']);
        $this->assertSame((float) $defaults['map']['lng'],  $missing['map']['lng']);
        $this->assertSame((int)   $defaults['map']['zoom'], $missing['map']['zoom']);

        // End-to-end persistence check: a payload at the very edge of
        // the controller's `between:` rule (lat=90, lng=-180, zoom=19)
        // survives validation and lands intact in site_pages.extra.
        $admin = $this->makeAdmin();
        $page  = $this->makeContactPage();
        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => '', 'email' => '', 'phone' => '', 'hours' => '',
                'social'  => [],
                'map'     => ['lat' => 90.0, 'lng' => -180.0, 'zoom' => 19, 'label' => ''],
            ]))
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();

        $page->refresh();
        // Cast to float to absorb the JSON int-vs-float round-trip
        // (Postgres' JSON column may decode 90.0 as 90); the important
        // guarantee is that the extreme valid values survive the
        // controller's `between:` rule and the normaliser's clamp.
        $this->assertSame(90.0,   (float) $page->extra['map']['lat']);
        $this->assertSame(-180.0, (float) $page->extra['map']['lng']);
        $this->assertSame(19,     (int)   $page->extra['map']['zoom']);
    }

    public function test_unknown_social_platforms_are_dropped(): void
    {
        // The normaliser only persists the five known platforms — any
        // bonus key submitted by a tampered form must be discarded so
        // the public Blade view always sees a predictable shape.
        $admin = $this->makeAdmin();
        $page  = $this->makeContactPage();

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => '', 'email' => '', 'phone' => '', 'hours' => '',
                'social'  => [
                    'twitter'   => 'https://twitter.com/onein',
                    'instagram' => '',
                    'linkedin'  => '',
                    'youtube'   => '',
                    'facebook'  => '',
                    // The controller's validator only whitelists the five
                    // known keys; an unknown sub-key is silently ignored
                    // by Laravel's `array` rule, then dropped here too.
                    'tiktok'    => 'https://tiktok.com/@onein',
                ],
                'map' => ['lat' => 0, 'lng' => 0, 'zoom' => 2, 'label' => ''],
            ]))
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame(
            ['twitter', 'instagram', 'linkedin', 'youtube', 'facebook'],
            array_keys($page->extra['social'])
        );
        $this->assertArrayNotHasKey('tiktok', $page->extra['social']);
    }

    public function test_invalid_social_url_is_rejected_by_validation(): void
    {
        // The controller requires social URLs to start with http(s)://
        // — defense in depth so we never persist a `javascript:` URL
        // that the public template would happily render into an
        // anchor's href.
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        $resp = $this->actingAs($admin, 'admin')
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => '', 'email' => '', 'phone' => '', 'hours' => '',
                'social'  => [
                    'twitter'   => 'javascript:alert(1)',
                    'instagram' => '', 'linkedin' => '', 'youtube' => '', 'facebook' => '',
                ],
                'map' => ['lat' => 0, 'lng' => 0, 'zoom' => 2, 'label' => ''],
            ]));

        $resp->assertStatus(422);
        $errors = (array) ($resp->json('errors') ?? []);
        $this->assertArrayHasKey('extra.social.twitter', $errors);
    }

    public function test_public_contact_page_renders_every_saved_extra_value(): void
    {
        // Mirror of AboutSitePageEditorTest's public-render assertion: a
        // future change to resources/views/public/contact.blade.php that
        // accidentally drops a field (e.g. removes the YouTube icon from
        // $socialIcons, or hard-codes the seeded Hyderabad address) would
        // still pass the persistence test above but render the wrong
        // page. This test walks the rendered HTML and checks every
        // saved value actually appears.
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        $payload = $this->payload([
            'address' => "1INME HQ\n42 Example Street\nBengaluru 560001",
            'email'   => 'hello@example.com',
            'phone'   => '+91 40 9876 5432',
            'hours'   => "Mon–Fri · 09:00 – 17:00",
            'social'  => [
                'twitter'   => 'https://twitter.com/onein',
                'instagram' => 'https://instagram.com/onein',
                'linkedin'  => 'https://linkedin.com/company/onein',
                'youtube'   => 'https://youtube.com/@onein',
                'facebook'  => 'https://facebook.com/onein',
            ],
            'map' => [
                'lat'   => 12.9716,
                'lng'   => 77.5946,
                'zoom'  => 12,
                'label' => 'Bengaluru office',
            ],
        ]);

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $payload)
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();

        $publicResp = $this->get('/contact');
        $publicResp->assertOk();

        // Every line of the multi-line address survives nl2br + e().
        $publicResp->assertSee('1INME HQ', false);
        $publicResp->assertSee('42 Example Street', false);
        $publicResp->assertSee('Bengaluru 560001', false);

        // Email rendered both as anchor href and as visible text.
        $publicResp->assertSee('mailto:hello@example.com', false);
        $publicResp->assertSee('hello@example.com', false);

        // Phone — visible exactly as entered, plus a tel: href with all
        // non-digit/non-plus characters stripped.
        $publicResp->assertSee('+91 40 9876 5432', false);
        $publicResp->assertSee('tel:+914098765432', false);

        // Hours — both lines of the (admittedly single-line) value.
        $publicResp->assertSee('Mon–Fri · 09:00 – 17:00', false);

        // Every non-empty social URL must reach the rendered page —
        // catches a future regression that drops one platform from the
        // $socialIcons whitelist in the Blade view.
        $publicResp->assertSee('https://twitter.com/onein', false);
        $publicResp->assertSee('https://instagram.com/onein', false);
        $publicResp->assertSee('https://linkedin.com/company/onein', false);
        $publicResp->assertSee('https://youtube.com/@onein', false);
        $publicResp->assertSee('https://facebook.com/onein', false);
        // The corresponding icon titles confirm each anchor is the
        // right platform (catches a copy/paste swap where two keys
        // share an icon).
        $publicResp->assertSee('title="X (Twitter)"', false);
        $publicResp->assertSee('title="Instagram"', false);
        $publicResp->assertSee('title="LinkedIn"', false);
        $publicResp->assertSee('title="YouTube"', false);
        $publicResp->assertSee('title="Facebook"', false);

        // Map: lat/lng/zoom are surfaced via data-* attributes that the
        // Leaflet bootstrap script reads, and the label is rendered
        // both as a data attribute and as visible caption text.
        $publicResp->assertSee('data-lat="12.9716"', false);
        $publicResp->assertSee('data-lng="77.5946"', false);
        $publicResp->assertSee('data-zoom="12"', false);
        $publicResp->assertSee('data-label="Bengaluru office"', false);
        $publicResp->assertSee('Bengaluru office', false); // visible caption + aria-label
        // The "View larger map" link encodes the same coordinates and
        // zoom into the OpenStreetMap permalink — guards against a
        // regression that decouples the link from the map. The view
        // formats coordinates with sprintf('%F', ...) which always
        // pads to 6 decimal places.
        $publicResp->assertSee('mlat=12.971600', false);
        $publicResp->assertSee('mlon=77.594600', false);
        $publicResp->assertSee('#map=12/12.971600/77.594600', false);
    }

    public function test_public_contact_page_omits_anchors_for_blank_social_keys(): void
    {
        // If the admin clears (say) youtube + facebook, the Blade view
        // must skip those <a> elements entirely — never render
        // `<a href="">` placeholders. An empty anchor would still be
        // clickable in the layout's grid and would point at the same
        // page, which is both a UX and a (mild) crawl/SEO hazard.
        $admin = $this->makeAdmin();
        $this->makeContactPage();

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/contact', $this->payload([
                'address' => 'Some address line',
                'email'   => '',
                'phone'   => '',
                'hours'   => '',
                'social'  => [
                    'twitter'   => 'https://twitter.com/onein',
                    'instagram' => '',
                    'linkedin'  => '',
                    'youtube'   => '',
                    'facebook'  => '',
                ],
                'map' => ['lat' => 0, 'lng' => 0, 'zoom' => 2, 'label' => ''],
            ]))
            ->assertRedirect(route('admin.site-pages.edit', 'contact'))
            ->assertSessionHasNoErrors();

        $publicResp = $this->get('/contact');
        $publicResp->assertOk();

        // The one populated platform still renders.
        $publicResp->assertSee('https://twitter.com/onein', false);
        $publicResp->assertSee('title="X (Twitter)"', false);

        // Each blank platform's icon title must NOT appear — those
        // titles only render inside the `@if($url !== '')` branch, so
        // their absence proves the empty <a> wasn't emitted either.
        $publicResp->assertDontSee('title="Instagram"', false);
        $publicResp->assertDontSee('title="LinkedIn"', false);
        $publicResp->assertDontSee('title="YouTube"', false);
        $publicResp->assertDontSee('title="Facebook"', false);

        // Belt-and-suspenders: assert the rendered HTML contains no
        // empty-href anchor at all. The contact view's other anchors
        // (mailto:, tel:, OSM permalink, form submit URL, layout nav)
        // are always non-empty when populated, so a stray `href=""`
        // would have to come from a regressed social loop.
        $this->assertStringNotContainsString('href=""', $publicResp->getContent());
    }
}
