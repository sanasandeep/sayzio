<?php

namespace Tests\Feature;

use App\Modules\User\Controllers\BiolinkBlockController;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\PageBackground;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Giving a page type a background it never had.
 *
 * Reviews, Updates and Calendar pages have hardcoded page colours and no
 * way to change them. AI Chat has a light/dark control and nothing else.
 * Now that there is one renderer, they can all have the picker.
 *
 * Two things had to be got right, and they are what these tests are for.
 *
 * FIRST, the gate. Both the Appearance page and the page-settings save
 * path guarded on isBiolinkFamily(), which means "rendered by the biolink
 * page engine" and gates fifty-odd other call sites -- blocks, page
 * templates, verification, the performance coach. Widening THAT to give a
 * Reviews page a background would have dragged all of it along. The two
 * questions only looked like one question because, until the renderer was
 * extracted, they had the same answer. supportsPageBackground() is the
 * narrow one.
 *
 * And the gate decides who may post, not what they may write: that
 * endpoint persists the whole biolink page surface, including custom_css
 * and custom_js_head. A Reviews page may write its background and nothing
 * else.
 *
 * SECOND, opting in. These pages must look EXACTLY as they do today until
 * someone chooses otherwise. resolve() defaults an absent type to
 * 'gradient' -- correct for the biolink, where that is the default look,
 * and wrong here, where it would repaint every existing page with the
 * biolink's purple.
 */
class MorePageTypesCanChooseABackgroundTest extends TestCase
{
    use RefreshDatabase;

    /** Types that gained the picker in this change. */
    private const GAINED = ['reviews', 'updates', 'calendar'];

    private function user(): User
    {
        return User::create([
            'name'     => 'pb'.Str::random(4),
            'email'    => 'pb'.Str::random(10).'@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function link(User $u, string $type, array $biolink = []): Link
    {
        /** @var Link $link */
        $link = $u->links()->create([
            'user_id'   => $u->id,
            'type'      => $type,
            'alias'     => 'pb'.substr(Str::random(10), 0, 10),
            'is_active' => true,
        ]);
        if ($biolink !== []) {
            $link->settings = ['biolink' => $biolink];
            $link->save();
        }

        // Two of these types render a DIFFERENT view, or none at all, until
        // their related record exists: an ai_chat link with no companion
        // falls back to common.biolink (RedirectController:649), and a
        // calendar link with no Calendar row 404s. A test that skips this
        // asserts against the wrong page and passes for the wrong reason.
        if ($type === 'calendar') {
            \App\Modules\User\Models\Calendar::create([
                'link_id'   => $link->id,
                'user_id'   => $u->id,
                'title'     => 'Cal',
                'slug'      => $link->alias,
                'timezone'  => 'UTC',
                'is_public' => true,
            ]);
        }
        return $link;
    }

    /** Markers that prove the right view rendered, not a fallback. */
    private const VIEW_MARKER = [
        'reviews'  => '--star',
        'updates'  => 'updates-container',
        'calendar' => '--cal-accent',
    ];

    private function renderedPage(Link $link): string
    {
        $html = $this->get('/'.$link->alias)->assertOk()->getContent();

        $this->assertStringContainsString(self::VIEW_MARKER[$link->type], $html,
            "a {$link->type} link rendered some other view; this test would be worthless");

        return $html;
    }

    // ===== The gate =====

    /** The narrow capability is not the broad one. */
    public function test_choosing_a_background_is_a_narrower_question_than_the_page_engine(): void
    {
        $this->assertNotSame(
            Link::BIOLINK_FAMILY,
            Link::PAGE_BACKGROUND_TYPES,
            'if these are ever the same list, one of them is doing the other one\'s job'
        );

        // Every engine type keeps its background; the new ones are additions.
        foreach (Link::BIOLINK_FAMILY as $type) {
            $this->assertContains($type, Link::PAGE_BACKGROUND_TYPES,
                "{$type} renders through the biolink engine and must keep its background");
        }
        foreach (self::GAINED as $type) {
            $this->assertContains($type, Link::PAGE_BACKGROUND_TYPES);
        }
    }

    /** isBiolinkFamily is untouched, because widening it was the risk. */
    public function test_the_page_engine_list_did_not_grow(): void
    {
        $this->assertSame([
            'biolink', 'conversational', 'slides', 'ai_chat',
            'restaurant_menu', 'store_menu', 'service_booking',
        ], Link::BIOLINK_FAMILY,
            'adding a type here opts it into blocks, page templates, '
            .'verification and the performance coach, none of which this change wants');
    }

    /** A type that gained the picker can now save a background. */
    public function test_a_newly_capable_type_can_save_a_background(): void
    {
        foreach (self::GAINED as $type) {
            $user = $this->user();
            $link = $this->link($user, $type);

            $this->actingAs($user)
                ->post('/user/links/'.$link->id.'/page-settings', [
                    'background_type'  => 'color',
                    'background_color' => '#123456',
                ])
                ->assertSessionMissing('error');

            $bio = $link->refresh()->settings['biolink'] ?? [];
            $this->assertSame('color', $bio['background_type'] ?? null, "{$type} did not save");
            $this->assertSame('#123456', $bio['background_color'] ?? null);
        }
    }

    /** A type that did NOT gain it still cannot. */
    public function test_a_type_without_the_capability_is_still_refused(): void
    {
        foreach (['url', 'resume', 'paid_page', 'brand_kit'] as $type) {
            $user = $this->user();
            $link = $this->link($user, $type);

            $this->actingAs($user)
                ->post('/user/links/'.$link->id.'/page-settings', ['background_type' => 'color'])
                ->assertForbidden();
        }
    }

    /**
     * The gate says who may post. This says what they may write.
     *
     * The endpoint persists the whole biolink page surface. A Reviews page
     * writing custom_js_head is the risk this narrowing exists for -- but
     * custom code and custom branding are ALSO plan-gated, so asserting on
     * them would pass for a free-plan user whatever the narrowing did, and
     * prove nothing. The two fields below are ungated, so failing to strip
     * them is the only way this test can go green.
     */
    public function test_a_newly_capable_type_may_write_only_its_background(): void
    {
        foreach (self::GAINED as $type) {
            $user = $this->user();
            $link = $this->link($user, $type);

            $this->actingAs($user)->post('/user/links/'.$link->id.'/page-settings', [
                'background_type'  => 'color',
                'background_color' => '#123456',
                'font_family'      => 'Comic Sans MS',
                'button_color'     => '#ff0000',
            ]);

            $bio = $link->refresh()->settings['biolink'] ?? [];
            $this->assertSame('#123456', $bio['background_color'] ?? null);

            foreach (['font_family', 'button_color'] as $forbidden) {
                $this->assertArrayNotHasKey($forbidden, $bio,
                    "a {$type} page must not be able to store {$forbidden}: its renderer "
                    .'reads none of the page surface, and the same opening would let a '
                    .'paid account store custom_css and custom_js_head there too');
            }
        }
    }

    /** The biolink family keeps the whole surface it has always had. */
    public function test_the_biolink_family_keeps_its_full_page_surface(): void
    {
        $user = $this->user();
        $link = $this->link($user, 'biolink');

        $this->actingAs($user)->post('/user/links/'.$link->id.'/page-settings', [
            'background_type'      => 'color',
            'font_family'     => 'Comic Sans MS',
            'button_color'    => '#ff0000',
        ]);

        $bio = $link->refresh()->settings['biolink'] ?? [];
        $this->assertSame('Comic Sans MS', $bio['font_family'] ?? null,
            'narrowing the key set must not touch the type it was never about');
        $this->assertSame('#ff0000', $bio['button_color'] ?? null);
    }

    /** Whatever a narrowed type may write, a theme must be able to restore. */
    public function test_the_narrowed_key_set_is_the_renderer_field_list(): void
    {
        $lockedBackground = array_values(array_intersect(
            BiolinkBlockController::DESIGN_LOCKED_PAGE_KEYS,
            PageBackground::FIELDS
        ));

        foreach ($lockedBackground as $field) {
            $this->assertContains($field, PageBackground::FIELDS);
        }
        $this->assertNotEmpty($lockedBackground);
    }

    /**
     * The picker has to be REACHABLE, not just saveable.
     *
     * The Appearance page is the only place the background card lives. If
     * it 500s or 403s for a type that can now save a background, the type
     * has the capability and no way to use it.
     */
    public function test_the_appearance_page_opens_for_every_newly_capable_type(): void
    {
        foreach ([...self::GAINED, 'biolink'] as $type) {
            $user = $this->user();
            $link = $this->link($user, $type);

            $html = $this->actingAs($user)
                ->get('/user/links/'.$link->id.'/settings/appearance')
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString('Page background', $html,
                "the background card must be on the Appearance page for {$type}");

            // A control the type cannot save must not be on the page. Showing
            // one is a setting that silently does nothing -- the exact thing
            // this whole run of work has been removing.
            foreach (['Font Family', 'Floating Text', 'Stickers'] as $familyOnly) {
                if ($type === 'biolink') {
                    $this->assertStringContainsString($familyOnly, $html,
                        "the biolink must keep {$familyOnly}");
                } else {
                    $this->assertStringNotContainsString($familyOnly, $html,
                        "a {$type} page cannot save {$familyOnly}, so it must not be offered");
                }
            }
        }
    }

    // ===== Opting in =====

    /** No saved background means no background: resolve() must not default. */
    public function test_an_unchosen_background_is_not_a_default_background(): void
    {
        $this->assertFalse(PageBackground::chosen([]));
        $this->assertFalse(PageBackground::chosen(['background_color' => '#fff']));
        $this->assertFalse(PageBackground::chosen(['background_type' => '']));
        $this->assertTrue(PageBackground::chosen(['background_type' => 'color']));

        // ...and resolve() DOES default, which is exactly why chosen() exists.
        $this->assertSame('gradient', PageBackground::resolve([])['type']);
    }

    /**
     * Every renderer asks chosen() before resolve().
     *
     * A renderer that skipped this would repaint every existing page of
     * its type with the biolink's default purple gradient the moment this
     * shipped -- silently, on pages nobody had edited.
     */
    public function test_every_new_renderer_opts_in_rather_than_defaulting(): void
    {
        foreach ([
            'reviews-page', 'updates-page', 'calendar-page',
        ] as $view) {
            $body = file_get_contents(base_path("resources/views/common/{$view}.blade.php"));

            $this->assertStringContainsString('PageBackground::chosen', $body,
                "{$view} must ask whether a background was chosen");
            $this->assertMatchesRegularExpression('/\$pbOn \? .*PageBackground::resolve/s', $body,
                "{$view} must only resolve once a background was chosen");
            $this->assertStringContainsString("@if(\$pbOn)@include('common.page-background.layers')@endif", $body,
                "{$view} must render the shared layers, and only when chosen");
        }
    }

    /**
     * The page each type renders with nothing chosen still carries its own
     * colours -- the actual regression this change could cause.
     */
    public function test_an_unchosen_page_keeps_the_colours_it_has_always_had(): void
    {
        $expected = [
            'reviews'  => 'radial-gradient(1200px 600px at 50% -10%, #1c1430 0%, var(--bg) 60%)',
            'updates'  => 'background: #0e0c1a',
            'calendar' => 'background:#0b0e16',
        ];

        foreach ($expected as $type => $needle) {
            $user = $this->user();
            $link = $this->link($user, $type);

            $html = $this->renderedPage($link);

            $this->assertStringContainsString($needle, $html,
                "an unedited {$type} page must render exactly the colours it always did");
            $this->assertStringNotContainsString('bg-layer', $html,
                "an unedited {$type} page must not acquire a background layer");
        }
    }

    /** And a chosen background actually reaches the page. */
    public function test_a_chosen_background_reaches_every_new_type(): void
    {
        foreach (self::GAINED as $type) {
            $user = $this->user();
            $link = $this->link($user, $type, [
                'background_type'  => 'color',
                'background_color' => '#c8f7c5',
            ]);

            $html = $this->renderedPage($link);

            $this->assertStringContainsString('#c8f7c5', $html,
                "{$type} did not paint the chosen colour");
            $this->assertStringContainsString('bg-layer', $html,
                "{$type} did not render the shared background layer");
        }
    }

    /**
     * On a page with a light/dark scheme, a chosen background wins in BOTH.
     *
     * Letting the scheme repaint would silently discard the creator's
     * choice for every visitor whose OS was in the other mode -- which
     * looks exactly like the picker not working.
     */
    public function test_a_chosen_background_survives_the_light_dark_schemes(): void
    {
        foreach (['updates'] as $type) {
            $user = $this->user();
            $link = $this->link($user, $type, [
                'background_type'  => 'color',
                'background_color' => '#c8f7c5',
            ]);

            $html = $this->renderedPage($link);

            // No scheme rule may repaint the page once a background is chosen.
            $this->assertDoesNotMatchRegularExpression(
                '/(prefers-color-scheme: dark\)[^}]*\{[^}]*background\s*:|light-mode body \{\s*background)/',
                $html,
                "{$type} lets its colour scheme override the chosen background"
            );
        }
    }
}
