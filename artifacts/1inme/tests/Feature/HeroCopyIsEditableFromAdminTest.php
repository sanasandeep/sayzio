<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Support\SitePagesContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The landing hero's headline, subheading and proof badges come from
 * Marketing Settings.
 *
 * These three were hardcoded in the hero partial, which made the page's
 * most-rewritten copy the only copy that needed a release to change.
 *
 * Most of what is worth testing here is not "does the setting render" but the
 * two bits of markup the fields carry, because both take admin text and put
 * it on the page as HTML:
 *
 *   - the headline wraps one word in the brand gradient;
 *   - the subheading turns **runs** into bold white.
 *
 * Both escape first and add markup second. The escaping tests below are the
 * point of this file: a homepage headline is about as public as a string
 * gets, and the admin form is the one place someone could put a <script> tag
 * into it.
 */
class HeroCopyIsEditableFromAdminTest extends TestCase
{
    /**
     * These tests write settings, and settings live in a table shared by the
     * whole suite. Without this the first run leaves "Turn every scan into a
     * booking." in the testing database and every later run of the
     * nothing-is-configured test measures the leftovers -- which is exactly
     * how this file first failed.
     */
    use RefreshDatabase;

    private function renderHero(): string
    {
        return $this->get('/')->getContent();
    }

    public function test_the_shipped_copy_renders_when_nothing_is_configured(): void
    {
        $default = SitePagesContent::heroCopyDefault();

        $html = $this->renderHero();

        $this->assertStringContainsString('Never miss another', $html);
        $this->assertStringContainsString(
            '<span class="grad-text">customer</span>',
            $html,
            'the shipped headline should still colour its last word'
        );
        $this->assertStringContainsString('375,000+', $html);
        $this->assertNotSame('', $default['subheading']);
    }

    public function test_an_admin_headline_and_highlight_reach_the_page(): void
    {
        AppSetting::put('marketing_hero_copy', SitePagesContent::normalizeHeroCopy([
            'headline'   => 'Turn every scan into a booking.',
            'highlight'  => 'booking',
            'subheading' => 'Your page, your QR codes, **one link**.',
        ]));

        $html = $this->renderHero();

        $this->assertStringContainsString('Turn every scan into a ', $html);
        $this->assertStringContainsString('<span class="grad-text">booking</span>', $html);
        $this->assertStringContainsString('<strong class="text-white">one link</strong>', $html);
        $this->assertStringNotContainsString('Never miss another', $html);
    }

    public function test_admin_badges_reach_the_page(): void
    {
        AppSetting::put('marketing_hero_badges', SitePagesContent::normalizeHeroBadges([
            ['value' => '9,000+', 'label' => 'salons', 'tone' => 'accent', 'pulse' => true],
        ]));

        $html = $this->renderHero();

        $this->assertStringContainsString('9,000+', $html);
        $this->assertStringContainsString('salons', $html);
        $this->assertStringContainsString('pulse-dot', $html);
        $this->assertStringNotContainsString(
            '375,000+',
            $html,
            'a configured badge list replaces the shipped one rather than adding to it'
        );
    }

    /**
     * The whole reason the helpers exist rather than a raw {!! !!} of the
     * setting.
     */
    public function test_markup_typed_into_the_fields_is_escaped(): void
    {
        $copy = SitePagesContent::normalizeHeroCopy([
            'headline'   => 'Hello <script>alert(1)</script>',
            'highlight'  => 'Hello',
            'subheading' => '<img src=x onerror=alert(1)> and **bold**',
        ]);

        $headline = SitePagesContent::heroHeadlineHtml($copy);
        $sub      = SitePagesContent::heroSubheadingHtml($copy);

        $this->assertStringNotContainsString('<script', $headline);
        $this->assertStringContainsString('&lt;script&gt;', $headline);
        $this->assertStringContainsString('<span class="grad-text">Hello</span>', $headline);

        $this->assertStringNotContainsString('<img', $sub);
        $this->assertStringContainsString('&lt;img', $sub);
        // The one tag the subheading IS allowed to produce still works.
        $this->assertStringContainsString('<strong class="text-white">bold</strong>', $sub);
    }

    /**
     * An ampersand in both the headline and the highlight must still match.
     * The search runs on the ESCAPED headline, so the needle has to be
     * escaped too -- get that wrong and "Q&A" silently stops highlighting.
     */
    public function test_the_highlight_matches_through_escaping(): void
    {
        $html = SitePagesContent::heroHeadlineHtml([
            'headline'  => 'Fast Q&A, every time',
            'highlight' => 'Q&A',
        ]);

        $this->assertStringContainsString('<span class="grad-text">Q&amp;A</span>', $html);
    }

    public function test_a_highlight_that_is_not_in_the_headline_is_ignored(): void
    {
        $html = SitePagesContent::heroHeadlineHtml([
            'headline'  => 'Never miss another customer.',
            'highlight' => 'booking',
        ]);

        $this->assertSame('Never miss another customer.', $html);
        $this->assertStringNotContainsString('grad-text', $html);
    }

    public function test_only_the_first_occurrence_is_highlighted(): void
    {
        $html = SitePagesContent::heroHeadlineHtml([
            'headline'  => 'One link, one page, one you',
            'highlight' => 'one',
        ]);

        $this->assertSame(1, substr_count($html, 'grad-text'));
        // Case-insensitive match, but the headline's own casing is kept.
        $this->assertStringContainsString('<span class="grad-text">One</span> link', $html);
    }

    /**
     * An unclosed marker should stay visible as asterisks rather than bolding
     * everything after it.
     */
    public function test_an_unclosed_bold_marker_is_left_alone(): void
    {
        $html = SitePagesContent::heroSubheadingHtml([
            'subheading' => 'Free forever **no card and nothing else',
        ]);

        $this->assertStringNotContainsString('<strong', $html);
        $this->assertStringContainsString('**no card', $html);
    }

    public function test_blank_copy_falls_back_rather_than_emptying_the_hero(): void
    {
        $copy = SitePagesContent::normalizeHeroCopy([
            'headline'   => '   ',
            'subheading' => '',
            'highlight'  => '',
        ]);

        $default = SitePagesContent::heroCopyDefault();

        $this->assertSame($default['headline'], $copy['headline']);
        $this->assertSame($default['subheading'], $copy['subheading']);
        // Submitted-but-empty: clearing the box is how you ask for a headline
        // with no coloured word, so it must survive the save.
        $this->assertSame('', $copy['highlight']);
    }

    /**
     * The distinction the test above depends on, stated on its own: an absent
     * highlight is a site that has never been configured, and it gets the
     * shipped headline complete. Collapse the two and every Sayzio install
     * ships a hero with the colour missing until someone notices.
     */
    public function test_an_absent_highlight_is_not_the_same_as_a_cleared_one(): void
    {
        $default = SitePagesContent::heroCopyDefault();

        $this->assertSame(
            $default['highlight'],
            SitePagesContent::normalizeHeroCopy([])['highlight'],
            'nothing saved yet should render the shipped headline, coloured word included'
        );

        $this->assertSame(
            '',
            SitePagesContent::normalizeHeroCopy(['highlight' => ''])['highlight'],
            'a cleared box should stay cleared'
        );
    }

    public function test_badges_are_capped_and_tones_are_constrained(): void
    {
        $badges = SitePagesContent::normalizeHeroBadges([
            ['value' => 'a', 'tone' => 'green'],
            ['value' => 'b', 'tone' => 'brand'],
            ['value' => 'c', 'tone' => 'accent'],
            ['value' => 'd', 'tone' => 'neon-pink'],
            ['value' => 'e', 'tone' => 'green'],
            ['value' => '', 'label' => ''],
        ]);

        $this->assertCount(4, $badges, 'at most four badges fit on one row');
        $this->assertSame(
            'green',
            $badges[3]['tone'],
            'an unknown tone falls back to a real one rather than rendering a dot with no colour'
        );
    }

    public function test_the_admin_form_posts_the_hero_fields(): void
    {
        $view = (string) file_get_contents(
            resource_path('views/admin/marketing-settings/index.blade.php')
        );

        foreach (['hero_copy[headline]', 'hero_copy[highlight]', 'hero_copy[subheading]'] as $field) {
            $this->assertStringContainsString(
                'name="' . $field . '"',
                $view,
                $field . ' is missing from Marketing Settings, so the hero is back to being deploy-only'
            );
        }

        $this->assertStringContainsString("'hero_badges['+i+'][value]'", $view);
        $this->assertStringContainsString("'hero_badges['+i+'][tone]'", $view);
    }

    /**
     * The whole path, once: open the screen, submit it, see the homepage
     * change.
     *
     * The unit tests above prove the normalizers behave. They cannot catch
     * the failure that actually costs Sana an afternoon -- a wrong validation
     * rule, which rejects the WHOLE form and makes every field on a long
     * screen un-saveable at once, with a message he has to scroll to find.
     * That is worth one slower test.
     */
    public function test_an_admin_can_save_the_hero_from_the_settings_screen(): void
    {
        $admin = $this->settingsAdmin();

        $this->actingAs($admin, 'admin')
            ->get('/admin/marketing-settings')
            ->assertOk()
            ->assertSee('Hero headline', false);

        $this->actingAs($admin, 'admin')
            ->put('/admin/marketing-settings', [
                // Required by the form; omitting it fails validation for
                // reasons that have nothing to do with the hero.
                'home_design' => 'classic',
                'hero_copy'   => [
                    'headline'   => 'Every scan becomes a booking.',
                    'highlight'  => 'booking',
                    'subheading' => 'Page, QR codes and short links. **Free forever**, no card.',
                ],
                'hero_badges' => [
                    ['value' => '9,000+',    'label' => 'salons',  'tone' => 'accent', 'pulse' => '1'],
                    ['value' => 'Free plan', 'label' => 'no card', 'tone' => 'green',  'pulse' => '0'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $html = $this->renderHero();

        $this->assertStringContainsString('Every scan becomes a ', $html);
        $this->assertStringContainsString('<span class="grad-text">booking</span>', $html);
        $this->assertStringContainsString('<strong class="text-white">Free forever</strong>', $html);
        $this->assertStringContainsString('9,000+', $html);
        $this->assertStringContainsString('Free plan', $html);
    }

    /** An admin who may edit settings. */
    private function settingsAdmin(): \App\Modules\Admin\Models\Admin
    {
        $role = \App\Modules\Admin\Models\Role::firstOrCreate(
            ['slug' => 'staff-settings-manage'],
            ['name' => 'Staff (settings.manage)', 'guard' => 'admin']
        );
        $perm = \App\Modules\Admin\Models\Permission::firstOrCreate(
            ['slug' => 'settings.manage'],
            ['name' => 'settings.manage', 'group' => 'settings']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);

        return \App\Modules\Admin\Models\Admin::create([
            'name'     => 'Admin ' . \Illuminate\Support\Str::random(4),
            'email'    => 'a' . \Illuminate\Support\Str::random(8) . '@admin.test',
            'password' => \Illuminate\Support\Facades\Hash::make('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    /**
     * An edit that never reaches a visitor is the same as no edit. The
     * marketing pages are cached, and the cache warms its settings from a
     * fixed key list.
     */
    public function test_the_new_keys_are_warmed_with_the_marketing_pages(): void
    {
        $keys = \App\Modules\Common\Support\MarketingPageCache::LAYOUT_SETTING_KEYS;

        $this->assertContains('marketing_hero_copy', $keys);
        $this->assertContains('marketing_hero_badges', $keys);
    }
}
