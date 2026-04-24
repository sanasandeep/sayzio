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
 * Feature coverage for the /about page editor (admin) and the public /about
 * render. Ensures every editable group on the About page survives a full
 * save → render round-trip and that validation rules reject obviously
 * broken input. The About editor has a lot of fields (hero badge/icon/
 * stats, value cards, three story images, six section titles, CTA
 * buttons), so this guards against a future schema change silently
 * dropping them or breaking the public page.
 */
class AboutSitePageEditorTest extends TestCase
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

    private function makeAboutPage(): SitePage
    {
        // The About page is seeded by an earlier migration — refresh its
        // content here so each test starts from a known shape.
        $page = SitePage::firstOrNew(['slug' => 'about']);
        $page->fill([
            'title'            => 'About 1INME',
            'meta_description' => 'Seeded about page.',
            'sections'         => SitePagesContent::aboutSectionsDefault(),
            'extra'            => SitePagesContent::aboutExtraDefault(),
        ])->save();
        return $page;
    }

    /**
     * A fully-populated payload that touches every group the editor
     * exposes. Used by the round-trip and public-render assertions.
     */
    private function fullPayload(): array
    {
        return [
            'title'            => 'About 1INME — Edited',
            'meta_description' => 'Updated about description.',
            'sections'         => [
                ['id' => '', 'heading' => 'About 1INME', 'body' => 'Intro body copy.', 'visible' => '1'],
                ['id' => '', 'heading' => 'Our story',   'body' => 'Story body copy.', 'visible' => '1'],
            ],
            'cta_label' => 'Sign up',
            'cta_url'   => '/register',
            'extra'     => [
                'hero' => [
                    'badge_label'       => '  Hello world  ', // whitespace must be trimmed
                    'badge_icon'        => 'fa-star',
                    'side_image'        => '/img/custom-hero.png',
                    'side_image_alt'    => 'Hero alt text',
                    'location_title'    => 'Bengaluru · India',
                    'location_subtitle' => 'Hybrid',
                    'location_icon'     => 'fa-map',
                    'stats' => [
                        ['value' => '500', 'suffix' => 'k+', 'label' => 'Visitors', 'visible' => '1'],
                        // visible:0 must round-trip to false (not be coerced to true)
                        ['value' => '4',   'suffix' => '',   'label' => 'Years',    'visible' => '0'],
                        // fully-blank repeater row must be dropped
                        ['value' => '',    'suffix' => '',   'label' => '',         'visible' => '1'],
                        ['value' => '12',  'suffix' => '',   'label' => 'Teams',    'visible' => '1'],
                    ],
                ],
                'values' => [
                    'heading'    => 'Our values',
                    'subheading' => '  Four ideas  ',
                    'cards' => [
                        ['icon' => 'fa-bolt',  'title' => 'Quick', 'desc' => 'Ship weekly.'],
                        // blank card row must be dropped
                        ['icon' => '',         'title' => '',      'desc' => ''],
                        ['icon' => 'fa-users', 'title' => 'Care',  'desc' => 'Listen first.'],
                    ],
                ],
                'story_images' => [
                    'office'    => ['url' => '/img/office.jpg',   'alt' => 'Studio'],
                    'values'    => ['url' => '/img/values.jpg',   'alt' => 'Working'],
                    'team_band' => ['url' => '/img/team-band.jpg', 'alt' => 'Team band'],
                ],
                'section_titles' => [
                    'founder'             => 'The founder',
                    'co_founders'         => 'Our co-founders',
                    'team_title'          => 'The crew',
                    'team_subtitle'       => 'Wonderful folks.',
                    'milestones_title'    => 'Our journey',
                    'milestones_subtitle' => 'How we got here.',
                ],
                'cta' => [
                    'heading'         => 'Build with us',
                    'body'            => 'We love hearing from you.',
                    'primary_label'   => 'Start now',
                    'primary_url'     => 'https://example.com/start',
                    'secondary_label' => 'Talk to us',
                    'secondary_url'   => '/contact',
                ],
                'founder' => [
                    'name'  => 'Aarav Reddy',
                    'role'  => 'Founder & CEO',
                    'photo' => '',
                    'bio'   => 'Founder bio copy.',
                    'links' => [
                        'twitter'  => 'https://twitter.com/aarav',
                        'linkedin' => '',
                    ],
                ],
                'co_founders' => [
                    [
                        'name' => 'Meera Iyer', 'role' => 'CTO',
                        'photo' => '', 'bio' => 'Co-founder bio.',
                        'links' => ['twitter' => '', 'linkedin' => ''],
                    ],
                ],
                'team' => [
                    ['name' => 'Karthik Rao', 'role' => 'Senior Engineer', 'photo' => '', 'bio' => 'Backend.'],
                ],
                'milestones' => [
                    ['date' => '2024-01', 'title' => 'Launch', 'description' => 'We launched the beta.'],
                ],
            ],
        ];
    }

    public function test_admin_save_round_trips_every_about_extra_group(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeAboutPage();

        $payload = $this->fullPayload();

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/about', $payload);

        $resp->assertRedirect(route('admin.site-pages.edit', 'about'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertIsArray($page->extra);

        // The persisted `extra` should equal what normalizeAboutExtra
        // produces for the same input — i.e. a true round-trip.
        $expected = SitePagesContent::normalizeAboutExtra($payload['extra']);
        $stored = $page->extra;
        // The controller may add a `blog_block` sub-key from a separate
        // input — strip it here so we compare the About-specific shape.
        unset($stored['blog_block']);
        $this->assertEquals($expected, $stored);

        // Specific guarantees called out in the task: trims whitespace,
        // drops fully-blank repeater rows, preserves the visible flag.
        $this->assertSame('Hello world', $stored['hero']['badge_label']);
        $this->assertSame('Four ideas',   $stored['values']['subheading']);
        $this->assertCount(3, $stored['hero']['stats']); // blank stat row dropped
        $this->assertSame(
            ['Visitors', 'Years', 'Teams'],
            array_column($stored['hero']['stats'], 'label')
        );
        $this->assertTrue($stored['hero']['stats'][0]['visible']);
        $this->assertFalse($stored['hero']['stats'][1]['visible']); // visible:0 preserved
        $this->assertTrue($stored['hero']['stats'][2]['visible']);
        $this->assertCount(2, $stored['values']['cards']); // blank value-card row dropped
        $this->assertSame(['Quick', 'Care'], array_column($stored['values']['cards'], 'title'));

        // Caps: normalizeAboutExtra hard-caps the repeater leaf fields so
        // a future schema change can't push 10kb of text into the layout.
        $longInput = [
            'hero'   => ['stats' => [['value' => '1', 'suffix' => '', 'label' => str_repeat('a', 200), 'visible' => true]]],
            'values' => ['cards' => [['icon' => 'fa-bolt', 'title' => str_repeat('b', 400), 'desc' => str_repeat('c', 800)]]],
        ];
        $longOut = SitePagesContent::normalizeAboutExtra($longInput);
        $this->assertSame(120, mb_strlen($longOut['hero']['stats'][0]['label']));
        $this->assertSame(200, mb_strlen($longOut['values']['cards'][0]['title']));
        $this->assertSame(500, mb_strlen($longOut['values']['cards'][0]['desc']));

        // Public /about renders every saved value.
        $publicResp = $this->get('/about');
        $publicResp->assertOk();
        $publicResp->assertSee('Hello world', false);          // hero badge
        $publicResp->assertSee('Bengaluru · India', false);    // hero location
        $publicResp->assertSee('/img/custom-hero.png', false); // hero side image
        $publicResp->assertSee('Our values', false);           // values heading
        $publicResp->assertSee('Quick', false);                // value card
        $publicResp->assertSee('Care', false);                 // value card
        $publicResp->assertSee('/img/office.jpg', false);      // story image
        $publicResp->assertSee('/img/values.jpg', false);      // story image
        $publicResp->assertSee('/img/team-band.jpg', false);   // story image
        $publicResp->assertSee('The crew', false);             // section title
        $publicResp->assertSee('Wonderful folks.', false);     // section subtitle
        $publicResp->assertSee('Our journey', false);          // milestones title
        $publicResp->assertSee('Build with us', false);        // CTA heading
        $publicResp->assertSee('Start now', false);            // CTA primary label
        $publicResp->assertSee('https://example.com/start', false); // CTA primary URL
        $publicResp->assertSee('Talk to us', false);           // CTA secondary label
        $publicResp->assertSee('Aarav Reddy', false);          // founder
        $publicResp->assertSee('Meera Iyer', false);           // co-founder
        $publicResp->assertSee('Karthik Rao', false);          // team
        $publicResp->assertSee('Launch', false);               // milestone
    }

    public function test_blank_about_extra_falls_back_to_bundled_images_and_named_routes(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAboutPage();

        // Save with nearly every editable field blank — the public page
        // must still render and substitute bundled images / named-route
        // URLs for the empty image/CTA fields.
        $payload = [
            'title'            => 'About 1INME',
            'meta_description' => '',
            'sections'         => [
                // Two sections so the public template renders the
                // "story" block (where the office/values story images
                // live). Without this, those images would be elided
                // entirely and the bundled-fallback assertions below
                // wouldn't have anything to match against.
                ['id' => '', 'heading' => 'About 1INME', 'body' => 'Intro body.', 'visible' => '1'],
                ['id' => '', 'heading' => 'Our story',   'body' => 'Story body.', 'visible' => '1'],
            ],
            'cta_label' => '',
            'cta_url'   => '',
            'extra'     => [
                'hero' => [
                    'badge_label' => '', 'badge_icon' => '',
                    'side_image' => '', 'side_image_alt' => '',
                    'location_title' => '', 'location_subtitle' => '', 'location_icon' => '',
                    'stats' => [],
                ],
                'values'       => ['heading' => '', 'subheading' => '', 'cards' => []],
                'story_images' => [
                    'office'    => ['url' => '', 'alt' => ''],
                    'values'    => ['url' => '', 'alt' => ''],
                    'team_band' => ['url' => '', 'alt' => ''],
                ],
                'section_titles' => [],
                'cta' => [
                    'heading'         => 'CTA heading',
                    'body'            => 'CTA body copy.',
                    'primary_label'   => 'Primary label',
                    'primary_url'     => '',
                    'secondary_label' => 'Secondary label',
                    'secondary_url'   => '',
                ],
            ],
        ];

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/about', $payload)
            ->assertRedirect(route('admin.site-pages.edit', 'about'))
            ->assertSessionHasNoErrors();

        $publicResp = $this->get('/about');
        $publicResp->assertOk();

        // Bundled image fallbacks (asset() URL contains this path).
        $publicResp->assertSee('images/marketing/about/hero.png', false);
        $publicResp->assertSee('images/marketing/about/office.png', false);
        $publicResp->assertSee('images/marketing/about/values.png', false);
        $publicResp->assertSee('images/marketing/about/team.png', false);

        // Named-route fallbacks for blank CTA URLs.
        $publicResp->assertSee(route('register.page'), false);
        $publicResp->assertSee(route('site.contact'), false);
    }

    public function test_admin_can_hide_individual_lower_about_sections(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeAboutPage();

        // Hide the founder + co-founders + cta sections; the rest stay
        // visible. We submit a string '0'/'1' (matches the editor's
        // hidden inputs) to make sure the validator + normalizer accept
        // both shapes.
        $payload = $this->fullPayload();
        $payload['extra']['section_visibility'] = [
            'story'       => '1',
            'team_band'   => '1',
            'founder'     => '0',
            'co_founders' => '0',
            'team'        => '1',
            'milestones'  => '1',
            'cta'         => '0',
        ];

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/about', $payload);
        $resp->assertRedirect(route('admin.site-pages.edit', 'about'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $stored = $page->extra;
        $this->assertIsArray($stored);
        $this->assertArrayHasKey('section_visibility', $stored);
        // Booleans are normalized to real booleans.
        $this->assertTrue($stored['section_visibility']['story']);
        $this->assertFalse($stored['section_visibility']['founder']);
        $this->assertFalse($stored['section_visibility']['co_founders']);
        $this->assertFalse($stored['section_visibility']['cta']);
        $this->assertTrue($stored['section_visibility']['team']);

        // Public /about must skip the hidden sections entirely while
        // still rendering the visible ones.
        $publicResp = $this->get('/about');
        $publicResp->assertOk();
        $publicResp->assertSee('Karthik Rao', false);    // team — visible
        $publicResp->assertSee('Launch', false);          // milestones — visible
        $publicResp->assertDontSee('Aarav Reddy', false); // founder — hidden
        $publicResp->assertDontSee('Meera Iyer', false);  // co-founder — hidden
        $publicResp->assertDontSee('Build with us', false); // CTA heading — hidden
        $publicResp->assertDontSee('Start now', false);     // CTA primary — hidden

        // Re-saving without a section_visibility key (e.g. an older
        // payload) must keep all sections visible — the default — so an
        // upgrade path never silently hides anything.
        $payloadWithout = $this->fullPayload();
        $this->assertArrayNotHasKey('section_visibility', $payloadWithout['extra']);
        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/about', $payloadWithout)
            ->assertRedirect(route('admin.site-pages.edit', 'about'))
            ->assertSessionHasNoErrors();
        $page->refresh();
        foreach (SitePagesContent::aboutLowerSectionSlugs() as $slug) {
            $this->assertTrue(
                $page->extra['section_visibility'][$slug] ?? null,
                "Section '$slug' should default to visible when no visibility map is submitted."
            );
        }
        $this->get('/about')->assertSee('Aarav Reddy', false);
    }

    /**
     * Minimal-but-valid /about update payload that lets each
     * section_order test vary just the slug list. Other extra fields are
     * left empty so the controller's normaliser fills in defaults.
     */
    private function payloadWithSectionOrder(array $sectionOrder): array
    {
        return [
            'title'            => 'About 1INME',
            'meta_description' => '',
            'sections'         => [
                ['id' => '', 'heading' => 'About 1INME', 'body' => 'Intro.', 'visible' => '1'],
            ],
            'cta_label' => '',
            'cta_url'   => '',
            'extra'     => [
                'section_order' => $sectionOrder,
            ],
        ];
    }

    public function test_about_section_order_empty_array_persists_as_empty(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeAboutPage();

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/about', $this->payloadWithSectionOrder([]))
            ->assertRedirect(route('admin.site-pages.edit', 'about'))
            ->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertIsArray($page->extra);
        $this->assertArrayHasKey('section_order', $page->extra);
        // An empty submitted list must NOT be padded — that signals the
        // admin hasn't customised the order, and the public template
        // falls back to the canonical default order itself.
        $this->assertSame([], $page->extra['section_order']);
    }

    public function test_about_section_order_partial_list_is_padded_to_full_set(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeAboutPage();

        // Submit two of the seven valid slugs in a non-default order;
        // the remaining five must be appended in canonical order so
        // the public page can never silently hide a section.
        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/about', $this->payloadWithSectionOrder(['cta', 'team']))
            ->assertRedirect(route('admin.site-pages.edit', 'about'))
            ->assertSessionHasNoErrors();

        $page->refresh();
        $expected = ['cta', 'team', 'story', 'team_band', 'founder', 'co_founders', 'milestones'];
        $this->assertSame($expected, $page->extra['section_order']);
        $this->assertCount(
            count(SitePagesContent::aboutLowerSectionSlugs()),
            $page->extra['section_order']
        );
    }

    public function test_about_section_order_drops_duplicates_and_pads_remainder(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeAboutPage();

        // Duplicates pass `in:` validation but must be collapsed by the
        // normaliser; the remaining slugs are appended in canonical
        // order. Whitespace-padded entries should also be trimmed.
        $payload = $this->payloadWithSectionOrder([
            'story',
            ' story ',  // whitespace duplicate
            'cta',
            'cta',
        ]);

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/about', $payload)
            ->assertRedirect(route('admin.site-pages.edit', 'about'))
            ->assertSessionHasNoErrors();

        $page->refresh();
        $expected = ['story', 'cta', 'team_band', 'founder', 'co_founders', 'team', 'milestones'];
        $this->assertSame($expected, $page->extra['section_order']);
    }

    public function test_about_section_order_normalizer_drops_invalid_and_duplicate_slugs(): void
    {
        // Defense-in-depth: even if a future schema change loosens the
        // controller `in:` rule, the normaliser itself must drop unknown
        // slugs, non-string entries, and duplicates, and pad missing
        // slugs in canonical order so no /about section is silently
        // hidden.
        $out = SitePagesContent::normalizeAboutExtra([
            'section_order' => [
                'story',
                'bogus_slug',  // unknown -> dropped
                'cta',
                42,            // non-string -> dropped
                'cta',         // duplicate -> dropped
                'team',
                null,          // non-string -> dropped
                ' founder ',   // whitespace trimmed
            ],
        ]);

        $expected = ['story', 'cta', 'team', 'founder', 'team_band', 'co_founders', 'milestones'];
        $this->assertSame($expected, $out['section_order']);
        $this->assertCount(
            count(SitePagesContent::aboutLowerSectionSlugs()),
            $out['section_order']
        );

        // Persistence-side check: a payload that survives validation
        // (the four valid entries below all pass `in:`) is normalised
        // before being written to site_pages.extra. Dupes are dropped
        // and the missing slugs are padded in canonical order.
        $admin = $this->makeAdmin();
        $page  = $this->makeAboutPage();

        $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/about', $this->payloadWithSectionOrder([
                'story', 'cta', 'team', 'cta',
            ]))
            ->assertRedirect(route('admin.site-pages.edit', 'about'))
            ->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame(
            ['story', 'cta', 'team', 'team_band', 'founder', 'co_founders', 'milestones'],
            $page->extra['section_order']
        );
    }

    public function test_about_section_order_validation_rejects_unknown_slugs(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAboutPage();

        // The controller's `in:` rule must reject unknown slugs with a
        // 422 rather than letting them slip through to be silently
        // cleaned by the normaliser.
        $resp = $this->actingAs($admin, 'admin')
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/site-pages/about', $this->payloadWithSectionOrder(['story', 'bogus_slug', 'cta']));

        $resp->assertStatus(422);
        $errors = (array) ($resp->json('errors') ?? []);
        // Laravel reports validation failures on the failing array index.
        $this->assertArrayHasKey('extra.section_order.1', $errors);
    }

    public function test_about_section_order_validation_rejects_more_than_seven_entries(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAboutPage();

        // Eight slugs (one duplicate brings the count past the cap)
        // must be rejected by the `max:7` rule before the normaliser
        // ever sees the payload.
        $resp = $this->actingAs($admin, 'admin')
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/site-pages/about', $this->payloadWithSectionOrder([
                'story', 'team_band', 'founder', 'co_founders',
                'team', 'milestones', 'cta', 'cta',
            ]));

        $resp->assertStatus(422);
        $errors = (array) ($resp->json('errors') ?? []);
        $this->assertArrayHasKey('extra.section_order', $errors);
    }

    public function test_about_extra_validation_rejects_bad_urls_and_over_limits(): void
    {
        $admin = $this->makeAdmin();
        $this->makeAboutPage();

        $payload = [
            'title'            => 'About 1INME',
            'meta_description' => '',
            'sections'         => [],
            'extra'            => [
                'hero' => [
                    // Fails the `^(/|https?://)` URL regex.
                    'side_image'  => 'not-a-url-or-path',
                    // Exceeds the 60-char cap.
                    'badge_label' => str_repeat('x', 200),
                    // 7 stats — over the max-of-6 cap.
                    'stats' => array_fill(0, 7, [
                        'value' => '1', 'suffix' => '', 'label' => 'L', 'visible' => '1',
                    ]),
                ],
                'values' => [
                    // 9 cards — over the max-of-8 cap.
                    'cards' => array_fill(0, 9, [
                        'icon' => 'fa-star', 'title' => 'T', 'desc' => 'D',
                    ]),
                ],
                'cta' => [
                    // Fails the `^(/|https?://)` URL regex.
                    'primary_url' => 'javascript:alert(1)',
                ],
                'founder' => [
                    'links' => [
                        // Fails the strict `^https?://` social-link regex.
                        'twitter' => 'twitter.com/no-scheme',
                    ],
                ],
            ],
        ];

        $resp = $this->actingAs($admin, 'admin')
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/site-pages/about', $payload);

        $resp->assertStatus(422);
        $errors = (array) ($resp->json('errors') ?? []);
        $this->assertArrayHasKey('extra.hero.side_image',         $errors);
        $this->assertArrayHasKey('extra.hero.badge_label',        $errors);
        $this->assertArrayHasKey('extra.hero.stats',              $errors);
        $this->assertArrayHasKey('extra.values.cards',            $errors);
        $this->assertArrayHasKey('extra.cta.primary_url',         $errors);
        $this->assertArrayHasKey('extra.founder.links.twitter',   $errors);
    }
}
