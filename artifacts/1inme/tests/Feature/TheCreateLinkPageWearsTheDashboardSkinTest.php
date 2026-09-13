<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Tests\TestCase;

/**
 * The Create Link page kept the treatment the rest of the dashboard gave up:
 * gradient card grounds, blurred ambient glows, drop shadows, a hover lift, and
 * a different accent colour on every one of the eighteen link types.
 *
 * These tests assert against the RENDERED page, not the Blade source, because
 * this skin has now twice been "fixed" in a way that shipped green and changed
 * nothing on screen -- once by styling a theme scope the user's account does
 * not match, once by leaving three older rules standing that outranked the new
 * one. What a file contains is not evidence; what the response body contains
 * is.
 */
class TheCreateLinkPageWearsTheDashboardSkinTest extends TestCase
{
    private function page(): string
    {
        $user = User::factory()->create();

        $resp = $this->actingAs($user)
            ->followingRedirects()          // a fresh account is sent to onboarding first
            ->get(route('user.links.create'));

        $resp->assertOk();

        return $resp->getContent();
    }

    public function test_the_page_still_renders(): void
    {
        $html = $this->page();

        // The page's actual job, unchanged by a reskin: every link type in the
        // catalog is still offered, and the picker still submits.
        foreach (\App\Modules\User\Support\LinkTypeCategories::types() as $type) {
            $this->assertStringContainsString(
                'value="' . $type['value'] . '"',
                $html,
                "Link type {$type['value']} vanished from the picker."
            );
        }

        $this->assertStringContainsString('Guided wizard', $html);
        $this->assertStringContainsString('Build with AI', $html);
        $this->assertStringContainsString('name="alias"', $html);
    }

    public function test_no_card_lifts_glows_or_casts_a_shadow(): void
    {
        $html = $this->page();

        // The exact devices the rest of the dashboard stopped using. Each of
        // these was on this page before the reskin.
        $retired = [
            'hover:shadow-2xl'      => 'a drop shadow on hover',
            'shadow-blue-500/20'    => 'a coloured shadow',
            'shadow-blue-500/30'    => 'a coloured shadow',
            'shadow-blue-500/10'    => 'a coloured shadow',
            'blur-3xl'              => 'an ambient blurred glow',
            '-translate-y-0.5'      => 'a card lift on hover',
            'ring-blue-500/30'      => 'a selection ring',
            'bg-gradient-to-br from-blue-500/15' => 'a gradient card ground',
        ];

        foreach ($retired as $needle => $what) {
            $this->assertStringNotContainsString(
                $needle,
                $html,
                "The Create Link page still renders {$what} ({$needle})."
            );
        }
    }

    public function test_the_eighteen_types_no_longer_each_carry_their_own_colour(): void
    {
        $html = $this->page();

        // Narrow to the type grid itself. Two things otherwise answer for the
        // page and make a clean picker look dirty: the shared theme stylesheet
        // names every Tailwind colour utility so it can override it in light
        // mode, and the surrounding dashboard chrome has badges of its own (the
        // sidebar's amber PRO pill). Neither is this page painting a link type.
        $start = strpos($html, 'id="lt-card-');
        $end   = strpos($html, 'Pick a link type to continue');

        $this->assertNotFalse($start, 'The type grid did not render.');
        $this->assertNotFalse($end, 'The picker action bar did not render.');

        $markup = substr($html, $start, $end - $start);

        // Every badge tint in the catalog -- violet, emerald, amber, cyan, sky,
        // pink, fuchsia, orange, indigo, lime, rose, yellow, purple, teal. The
        // catalog still defines them (other surfaces read `badge`), but this
        // page must not paint with them.
        $painted = [];

        foreach (\App\Modules\User\Support\LinkTypeCategories::types() as $type) {
            foreach (explode(' ', $type['badge']) as $cls) {
                $cls = trim($cls);
                if ($cls !== '' && str_contains($markup, $cls)) {
                    $painted[] = $type['value'] . ' → ' . $cls;
                }
            }
        }

        $this->assertSame(
            [],
            $painted,
            "Per-type badge colour is still being painted on the picker:\n  " . implode("\n  ", $painted)
        );
    }

    public function test_the_skin_is_written_against_theme_tokens_not_one_theme(): void
    {
        $html = $this->page();

        // A hard-coded dark palette is how this page drifted from the dashboard
        // in the first place: it looked right in one scope and wrong in the
        // other three. The replacement must read the theme's own tokens.
        foreach (['--bg-card', '--border-glass', '--text-primary', '--text-dimmed', '--text-faint', '--accent'] as $token) {
            $this->assertStringContainsString(
                'var(' . $token . ')',
                $html,
                "The Create Link skin never reads {$token}, so it cannot follow the theme."
            );
        }
    }

    public function test_the_accent_is_spent_on_the_hover_plate_and_the_ribbon(): void
    {
        $html = $this->page();

        // The one brand moment: the icon plate under the pointer fills with the
        // Sayzio gradient. If this selector is gone the page is grey, not quiet.
        $this->assertStringContainsString('--cl-brand', $html);
        $this->assertStringContainsString('.cl-tile:hover .cl-ico', $html);

        // And the marketing ribbon rides the recommended card, the same partial
        // the dashboard and stats heroes use -- not a lookalike.
        $this->assertStringContainsString('cribbon', $html);
        $this->assertStringContainsString('cribbon-copy', $html);
    }

    public function test_the_stylesheet_closes_every_comment_it_opens(): void
    {
        $html = $this->page();

        // A comment closed early leaves prose in raw CSS and silently drops the
        // rule that follows it. That is exactly how the sidebar border went
        // missing through three consecutive "fixes".
        if (!preg_match_all('/<style>(.*?)<\/style>/s', $html, $m)) {
            $this->fail('The Create Link page rendered no <style> block at all.');
        }

        $css = implode("\n", $m[1]);

        $this->assertSame(
            substr_count($css, '/*'),
            substr_count($css, '*/'),
            'Unbalanced CSS comment markers — a rule is being swallowed.'
        );
        $this->assertSame(
            substr_count($css, '{'),
            substr_count($css, '}'),
            'Unbalanced CSS braces — a rule is being swallowed.'
        );
    }
}
