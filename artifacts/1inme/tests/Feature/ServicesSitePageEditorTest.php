<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\SitePage;
use Database\Seeders\SitePagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Feature coverage for the /services page editor (admin). The Services
 * editor uses a specialised section schema (tagline, icon, tint,
 * newline-separated bullets, CTA label/URL) that the generic
 * `normalizeSections` would strip — so SitePageController::update
 * carves out a dedicated code path for it. These tests guard against
 * a regression in:
 *  - bullets text being split on newlines into a clean array
 *  - the `cta_url` regex rule rejecting non-URL values
 *  - icon / tint / tagline fields round-tripping intact
 *  - fully-blank section rows being dropped
 *  - whitespace being trimmed on the trimmed leaf fields
 */
class ServicesSitePageEditorTest extends TestCase
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

    private function makeServicesPage(): SitePage
    {
        // The Services page is seeded by an earlier migration — refresh
        // its content here so each test starts from a known shape.
        $page = SitePage::firstOrNew(['slug' => 'services']);
        $page->fill([
            'title'            => 'What you can do with Sayzio',
            'meta_description' => 'Seeded services page.',
            'sections'         => SitePagesSeeder::servicesDefaultSections(),
            'cta_label'        => 'Create your Sayzio',
            'cta_url'          => '/register',
        ])->save();
        return $page;
    }

    /**
     * Minimal-but-valid Services update payload that lets each test
     * vary just the `sections` array.
     */
    private function payload(array $sections, array $overrides = []): array
    {
        return array_replace([
            'title'            => 'What you can do with Sayzio',
            'meta_description' => 'Updated services description.',
            'sections'         => $sections,
            'cta_label'        => 'Create your Sayzio',
            'cta_url'          => '/register',
        ], $overrides);
    }

    public function test_admin_save_round_trips_full_services_section_schema(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeServicesPage();

        // One fully-populated row that exercises every field on the
        // services section schema, including newline-separated bullets
        // (with a blank line and trim-able whitespace) that the
        // controller must split into a clean array.
        $payload = $this->payload([
            [
                'heading'   => '  Marketing channel  ',
                'tagline'   => '  Run campaigns from one trackable hub.  ',
                'body'      => "Body copy stays as-is.\nIncluding embedded newlines.",
                'icon'      => '  fa-bullhorn  ',
                'tint'      => '  from-violet-500/30 to-fuchsia-500/10  ',
                'bullets'   => "  Branded link-in-bio  \n\nPer-link click analytics\n  Lead-capture forms  \n",
                'cta_label' => '  Get started  ',
                'cta_url'   => '  /register  ',
            ],
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/services', $payload);

        $resp->assertRedirect(route('admin.site-pages.edit', 'services'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertIsArray($page->sections);
        $this->assertCount(1, $page->sections);

        $section = $page->sections[0];

        // Trimmed leaf fields: heading/tagline/icon/tint/cta_label/cta_url
        // are all `trim()`-ed before being persisted; `body` is NOT
        // trimmed so embedded newlines/leading spaces survive intact.
        $this->assertSame('Marketing channel',                          $section['heading']);
        $this->assertSame('Run campaigns from one trackable hub.',      $section['tagline']);
        $this->assertSame("Body copy stays as-is.\nIncluding embedded newlines.", $section['body']);
        $this->assertSame('fa-bullhorn',                                $section['icon']);
        $this->assertSame('from-violet-500/30 to-fuchsia-500/10',       $section['tint']);
        $this->assertSame('Get started',                                $section['cta_label']);
        $this->assertSame('/register',                                  $section['cta_url']);

        // Bullets: the controller splits on \r?\n, trims each line and
        // drops blank lines — so a textarea with mixed whitespace and
        // an empty line in the middle becomes a clean, contiguous array.
        $this->assertIsArray($section['bullets']);
        $this->assertSame(
            ['Branded link-in-bio', 'Per-link click analytics', 'Lead-capture forms'],
            $section['bullets']
        );

        // The services branch must NOT silently inherit generic-section
        // keys (id / visible) — those would change the public template's
        // rendering shape. Only the dedicated services keys are stored.
        $this->assertSame(
            ['heading', 'tagline', 'body', 'icon', 'tint', 'bullets', 'cta_label', 'cta_url'],
            array_keys($section)
        );
        $this->assertArrayNotHasKey('id',      $section);
        $this->assertArrayNotHasKey('visible', $section);
    }

    public function test_fully_blank_services_section_rows_are_dropped(): void
    {
        // Rows where both `heading` and `body` are blank (after trim)
        // must be dropped before persistence, so the public services
        // page never renders an empty card. Other fields on the row
        // (tagline, icon, bullets) are NOT enough to keep it alive —
        // matching the generic editor's "needs heading or body" rule.
        $admin = $this->makeAdmin();
        $page  = $this->makeServicesPage();

        $payload = $this->payload([
            [
                'heading'   => 'Keeper',
                'tagline'   => '',
                'body'      => 'Has a body, must survive.',
                'icon'      => '',
                'tint'      => '',
                'bullets'   => '',
                'cta_label' => '',
                'cta_url'   => '',
            ],
            // Fully-blank row -> dropped.
            [
                'heading'   => '   ',
                'tagline'   => '',
                'body'      => '   ',
                'icon'      => '',
                'tint'      => '',
                'bullets'   => '',
                'cta_label' => '',
                'cta_url'   => '',
            ],
            // Decoration-only row (tagline/icon/bullets but no
            // heading/body) -> also dropped, since the filter only
            // looks at heading + body.
            [
                'heading'   => '',
                'tagline'   => 'Tagline only',
                'body'      => '',
                'icon'      => 'fa-star',
                'tint'      => 'from-amber-500/30 to-violet-500/10',
                'bullets'   => "First\nSecond",
                'cta_label' => '',
                'cta_url'   => '',
            ],
            [
                'heading'   => 'Second keeper',
                'tagline'   => 'With tagline',
                'body'      => '',
                'icon'      => 'fa-id-badge',
                'tint'      => 'from-sky-500/30 to-violet-500/10',
                'bullets'   => "Alpha\nBeta\nGamma",
                'cta_label' => 'Learn more',
                'cta_url'   => 'https://example.com/learn',
            ],
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/services', $payload);

        $resp->assertRedirect(route('admin.site-pages.edit', 'services'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertCount(2, $page->sections);
        $this->assertSame(
            ['Keeper', 'Second keeper'],
            array_column($page->sections, 'heading')
        );

        // Bullets on the surviving second row must still be a clean
        // array — proving the bullets parser runs on every kept row,
        // not just the first.
        $this->assertSame(
            ['Alpha', 'Beta', 'Gamma'],
            $page->sections[1]['bullets']
        );

        // Empty-string fields on the first keeper round-trip to empty
        // strings (and bullets to an empty array) — the normaliser
        // never substitutes seeded defaults for blank inputs.
        $this->assertSame('',  $page->sections[0]['tagline']);
        $this->assertSame('',  $page->sections[0]['icon']);
        $this->assertSame('',  $page->sections[0]['tint']);
        $this->assertSame([],  $page->sections[0]['bullets']);
        $this->assertSame('',  $page->sections[0]['cta_label']);
        $this->assertSame('',  $page->sections[0]['cta_url']);
    }

    public function test_invalid_section_cta_url_is_rejected_by_validation(): void
    {
        // The per-section `cta_url` regex requires `/...` or `http(s)://...`
        // — defense in depth so a stored URL can never become e.g.
        // `javascript:alert(1)` and end up rendered as an anchor href on
        // the public services page.
        $admin = $this->makeAdmin();
        $this->makeServicesPage();

        $resp = $this->actingAs($admin, 'admin')
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/site-pages/services', $this->payload([
                [
                    'heading'   => 'Bad CTA',
                    'tagline'   => '',
                    'body'      => 'This row has an unsafe CTA URL.',
                    'icon'      => '',
                    'tint'      => '',
                    'bullets'   => '',
                    'cta_label' => 'Go',
                    'cta_url'   => 'javascript:alert(1)',
                ],
            ]));

        $resp->assertStatus(422);
        $errors = (array) ($resp->json('errors') ?? []);
        $this->assertArrayHasKey('sections.0.cta_url', $errors);
    }

    public function test_section_cta_url_accepts_root_relative_and_https_urls(): void
    {
        // Both formats accepted by the regex must survive unchanged:
        // a root-relative path (used by the seeded /register CTA) and
        // an absolute https URL (used when linking to an external site).
        $admin = $this->makeAdmin();
        $page  = $this->makeServicesPage();

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/services', $this->payload([
                [
                    'heading'   => 'Internal CTA',
                    'tagline'   => '',
                    'body'      => 'Links to a local route.',
                    'icon'      => '',
                    'tint'      => '',
                    'bullets'   => '',
                    'cta_label' => 'Sign up',
                    'cta_url'   => '/register',
                ],
                [
                    'heading'   => 'External CTA',
                    'tagline'   => '',
                    'body'      => 'Links to an absolute URL.',
                    'icon'      => '',
                    'tint'      => '',
                    'bullets'   => '',
                    'cta_label' => 'Read docs',
                    'cta_url'   => 'https://example.com/docs',
                ],
            ]));

        $resp->assertRedirect(route('admin.site-pages.edit', 'services'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertCount(2, $page->sections);
        $this->assertSame('/register',                $page->sections[0]['cta_url']);
        $this->assertSame('https://example.com/docs', $page->sections[1]['cta_url']);
    }

    public function test_bullets_string_is_split_on_crlf_and_trimmed(): void
    {
        // Browsers (notably on Windows) submit textarea content with
        // \r\n line endings — the parser uses /\r?\n/ specifically so
        // the carriage return doesn't end up wedged onto each bullet.
        $admin = $this->makeAdmin();
        $page  = $this->makeServicesPage();

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/services', $this->payload([
                [
                    'heading'   => 'CRLF bullets',
                    'tagline'   => '',
                    'body'      => 'Submitted from a CRLF browser.',
                    'icon'      => '',
                    'tint'      => '',
                    // Mix of \r\n and \n line endings, stray whitespace
                    // on each side, and a fully-blank middle line.
                    'bullets'   => "  First bullet  \r\n\r\n\tSecond bullet\r\n   \r\nThird bullet  ",
                    'cta_label' => '',
                    'cta_url'   => '',
                ],
            ]));

        $resp->assertRedirect(route('admin.site-pages.edit', 'services'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $bullets = $page->sections[0]['bullets'];
        $this->assertIsArray($bullets);
        $this->assertSame(['First bullet', 'Second bullet', 'Third bullet'], $bullets);
        // Defense against a regression where the parser stops trimming:
        // every bullet must be free of \r and surrounding whitespace.
        foreach ($bullets as $b) {
            $this->assertSame(trim($b), $b);
            $this->assertStringNotContainsString("\r", $b);
        }
    }

    public function test_blank_bullets_string_persists_as_empty_array(): void
    {
        // A bullets textarea that's empty (or whitespace-only) must
        // persist as an empty array — not as `['']` or as a string —
        // so the public template can iterate it without conditionally
        // skipping a single empty list item.
        $admin = $this->makeAdmin();
        $page  = $this->makeServicesPage();

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/services', $this->payload([
                [
                    'heading'   => 'No bullets',
                    'tagline'   => '',
                    'body'      => 'A row that intentionally lists nothing.',
                    'icon'      => '',
                    'tint'      => '',
                    'bullets'   => "   \n\n   \n",
                    'cta_label' => '',
                    'cta_url'   => '',
                ],
            ]));

        $resp->assertRedirect(route('admin.site-pages.edit', 'services'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertSame([], $page->sections[0]['bullets']);
    }
}
