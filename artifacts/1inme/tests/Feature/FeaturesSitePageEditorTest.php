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
 * Feature coverage for the /features page editor (admin). The Features
 * editor uses a category-structured schema (id/icon/heading/intro plus
 * a repeatable list of feature rows with name/description/link) that
 * goes through its own `SitePageController::updateFeatures` branch and
 * `SitePagesContent::normalizeFeaturesCategories`. These tests guard
 * against a regression in:
 *  - the full categories array (id/icon/heading/intro + nested feature
 *    rows) round-tripping through save → reload intact
 *  - the `categories.*.id` regex rule rejecting non-slug values
 *    (anything other than [a-z0-9-])
 *  - the `features.*.link` regex rule rejecting unsafe URLs (e.g.
 *    `javascript:alert(1)`) so a stored link can never become an
 *    XSS-flavoured anchor href on the public /features page
 *  - the normaliser dropping fully-blank category rows and
 *    fully-blank feature rows so the public page never renders an
 *    empty card or an empty bullet
 */
class FeaturesSitePageEditorTest extends TestCase
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

    private function makeFeaturesPage(): SitePage
    {
        // The Features page is seeded by an earlier migration — refresh
        // its content here so each test starts from the canonical
        // category-structured shape returned by SitePagesContent.
        $page = SitePage::firstOrNew(['slug' => 'features']);
        $page->fill([
            'title'            => 'Everything you can do with Sayzio',
            'meta_description' => 'Seeded features page.',
            'sections'         => SitePagesContent::featuresCategoriesDefault(),
        ])->save();
        return $page;
    }

    /**
     * Minimal-but-valid Features update payload that lets each test
     * vary just the `categories` array.
     */
    private function payload(array $categories, array $overrides = []): array
    {
        return array_replace([
            'title'            => 'Everything you can do with Sayzio',
            'meta_description' => 'Updated features description.',
            'categories'       => $categories,
        ], $overrides);
    }

    public function test_admin_save_round_trips_full_categories_schema(): void
    {
        $admin = $this->makeAdmin();
        $page  = $this->makeFeaturesPage();

        // Two categories that together exercise every editable field
        // on the features schema, including a nested feature row with
        // every key (name + description + link), surrounding whitespace
        // (which the normaliser must trim), and a custom slug-id.
        $payload = $this->payload([
            [
                'id'       => '  ai-suite  ',
                'icon'     => '  fa-robot  ',
                'heading'  => '  AI suite  ',
                'intro'    => '  A set of AI products that plug into your Sayzio.  ',
                'features' => [
                    [
                        'name'        => '  AI Chatbot  ',
                        'description' => '  Trained 24/7 chatbot on your biolink.  ',
                        'link'        => '  /ai-chatbot  ',
                    ],
                    [
                        'name'        => 'AI Voice Assistant',
                        'description' => 'AI receptionist that picks up calls.',
                        'link'        => 'https://example.com/voice',
                    ],
                ],
            ],
            [
                'id'       => 'biolink',
                'icon'     => 'fa-square-share-nodes',
                'heading'  => 'Biolink builder',
                'intro'    => 'Build a one-link landing page.',
                'features' => [
                    // A feature with an empty link must round-trip with
                    // an empty-string link (the field is nullable but
                    // the normaliser always emits the key for a stable
                    // public-template shape).
                    ['name' => 'Guided wizard', 'description' => 'Step-by-step flow.', 'link' => ''],
                ],
            ],
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/features', $payload);

        $resp->assertRedirect(route('admin.site-pages.edit', 'features'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();
        $this->assertIsArray($page->sections);
        $this->assertCount(2, $page->sections);

        // First category: every leaf field is trimmed, the custom
        // slug-id survives, and both nested feature rows round-trip
        // intact (also trimmed).
        $first = $page->sections[0];
        $this->assertSame(['id', 'icon', 'heading', 'intro', 'features'], array_keys($first));
        $this->assertSame('ai-suite',                                      $first['id']);
        $this->assertSame('fa-robot',                                      $first['icon']);
        $this->assertSame('AI suite',                                      $first['heading']);
        $this->assertSame('A set of AI products that plug into your Sayzio.', $first['intro']);
        $this->assertCount(2, $first['features']);
        $this->assertSame(
            ['name' => 'AI Chatbot', 'description' => 'Trained 24/7 chatbot on your biolink.', 'link' => '/ai-chatbot'],
            $first['features'][0]
        );
        $this->assertSame(
            ['name' => 'AI Voice Assistant', 'description' => 'AI receptionist that picks up calls.', 'link' => 'https://example.com/voice'],
            $first['features'][1]
        );

        // Second category: blank link round-trips as an empty string,
        // not null and not missing — the public template iterates
        // features and reads the `link` key unconditionally.
        $second = $page->sections[1];
        $this->assertSame('biolink',                $second['id']);
        $this->assertSame('fa-square-share-nodes',  $second['icon']);
        $this->assertSame('Biolink builder',        $second['heading']);
        $this->assertCount(1, $second['features']);
        $this->assertSame(
            ['name' => 'Guided wizard', 'description' => 'Step-by-step flow.', 'link' => ''],
            $second['features'][0]
        );
    }

    public function test_invalid_category_id_is_rejected_by_validation(): void
    {
        // The `categories.*.id` regex (`/^[a-z0-9\-]*$/i`) only allows
        // letters, digits and hyphens — anything else (spaces, dots,
        // slashes, special chars) must be rejected so an admin can
        // never accidentally save an id that breaks the public page's
        // anchor-link routing.
        $admin = $this->makeAdmin();
        $this->makeFeaturesPage();

        $resp = $this->actingAs($admin, 'admin')
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/site-pages/features', $this->payload([
                [
                    'id'       => 'has spaces and !',
                    'icon'     => 'fa-robot',
                    'heading'  => 'Bad slug',
                    'intro'    => 'This row has an id that breaks the regex.',
                    'features' => [
                        ['name' => 'Anything', 'description' => 'Anything.', 'link' => ''],
                    ],
                ],
            ]));

        $resp->assertStatus(422);
        $errors = (array) ($resp->json('errors') ?? []);
        $this->assertArrayHasKey('categories.0.id', $errors);
    }

    public function test_unsafe_feature_link_is_rejected_by_validation(): void
    {
        // The per-feature `link` regex requires `/...` or `http(s)://...`
        // — defense in depth so a stored link can never become e.g.
        // `javascript:alert(1)` and end up rendered as an anchor href
        // on the public /features page.
        $admin = $this->makeAdmin();
        $this->makeFeaturesPage();

        $resp = $this->actingAs($admin, 'admin')
            ->withHeaders(['Accept' => 'application/json'])
            ->put('/admin/site-pages/features', $this->payload([
                [
                    'id'       => 'ai-suite',
                    'icon'     => 'fa-robot',
                    'heading'  => 'AI suite',
                    'intro'    => 'A set of AI products.',
                    'features' => [
                        [
                            'name'        => 'Bad link',
                            'description' => 'This row has an unsafe link.',
                            'link'        => 'javascript:alert(1)',
                        ],
                    ],
                ],
            ]));

        $resp->assertStatus(422);
        $errors = (array) ($resp->json('errors') ?? []);
        $this->assertArrayHasKey('categories.0.features.0.link', $errors);
    }

    public function test_fully_blank_category_and_feature_rows_are_dropped(): void
    {
        // The normaliser must drop:
        //  - fully-blank category rows (heading + intro + features all
        //    empty), so the public page never renders an empty card
        //  - fully-blank feature rows inside a surviving category
        //    (name + description both empty), so a category with one
        //    blank row beneath a real one isn't rendered as a hole
        $admin = $this->makeAdmin();
        $page  = $this->makeFeaturesPage();

        $payload = $this->payload([
            // Keeper: a real category with a mix of real and blank
            // feature rows. The blank ones must be dropped while the
            // real ones survive in their original order.
            [
                'id'       => 'links',
                'icon'     => 'fa-link',
                'heading'  => 'Short links',
                'intro'    => 'Shorten and organise links.',
                'features' => [
                    ['name' => 'Real one',          'description' => 'Real description.', 'link' => '/real'],
                    // Fully blank feature row -> dropped.
                    ['name' => '   ',               'description' => '   ',               'link' => ''],
                    // Description-only is enough to keep the row alive
                    // (the filter only drops rows where BOTH name and
                    // description are blank after trim).
                    ['name' => '',                  'description' => 'Description-only.', 'link' => ''],
                    ['name' => 'Another real one',  'description' => 'Another desc.',     'link' => ''],
                ],
            ],
            // Fully-blank category row -> dropped.
            [
                'id'       => '',
                'icon'     => '',
                'heading'  => '   ',
                'intro'    => '   ',
                'features' => [
                    ['name' => '', 'description' => '', 'link' => ''],
                ],
            ],
            // Decoration-only category (icon set but no heading/intro
            // and every feature row is blank) -> also dropped, since
            // an empty `features` array combined with empty heading
            // and empty intro means there's nothing to render.
            [
                'id'       => '',
                'icon'     => 'fa-star',
                'heading'  => '',
                'intro'    => '',
                'features' => [
                    ['name' => '   ', 'description' => '   ', 'link' => ''],
                ],
            ],
            // Second keeper, to prove the filter doesn't stop after
            // the first dropped row.
            [
                'id'       => 'qr',
                'icon'     => 'fa-qrcode',
                'heading'  => 'QR studio',
                'intro'    => 'Generate scannable QR codes.',
                'features' => [
                    ['name' => 'Per-link QR', 'description' => 'Auto QR for every link.', 'link' => ''],
                ],
            ],
        ]);

        $resp = $this->actingAs($admin, 'admin')
            ->put('/admin/site-pages/features', $payload);

        $resp->assertRedirect(route('admin.site-pages.edit', 'features'));
        $resp->assertSessionHasNoErrors();

        $page->refresh();

        // Two surviving categories, in original order.
        $this->assertCount(2, $page->sections);
        $this->assertSame(
            ['Short links', 'QR studio'],
            array_column($page->sections, 'heading')
        );
        $this->assertSame(['links', 'qr'], array_column($page->sections, 'id'));

        // The first keeper kept its three non-blank features (the
        // fully-blank middle row was dropped) and the description-only
        // row survived with its blank name preserved as an empty
        // string.
        $features = $page->sections[0]['features'];
        $this->assertCount(3, $features);
        $this->assertSame(
            ['Real one', '', 'Another real one'],
            array_column($features, 'name')
        );
        $this->assertSame(
            ['Real description.', 'Description-only.', 'Another desc.'],
            array_column($features, 'description')
        );
        // The middle (description-only) row's link field is still
        // present as an empty string — the normaliser always emits
        // `link` so the public template can read it unconditionally.
        $this->assertSame('', $features[1]['link']);
    }
}
