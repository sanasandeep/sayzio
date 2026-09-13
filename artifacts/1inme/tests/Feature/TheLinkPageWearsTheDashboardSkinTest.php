<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Tests\TestCase;

/**
 * The link detail page carried its own design system in a local <style> block,
 * and that stylesheet never got the treatment the rest of the dashboard did:
 * gradient filter chips with a coloured glow under them, stat tiles on tinted
 * grounds with a top rail and a blurred orb, a lift on hover almost everywhere,
 * and twelve section cards each declaring its own accent colour inline.
 *
 * Asserted against the RENDERED page rather than the Blade source, for the same
 * reason as the Create Link guard: this class of change has shipped green and
 * changed nothing on screen more than once.
 */
class TheLinkPageWearsTheDashboardSkinTest extends TestCase
{
    /**
     * Rendered once and reused. This page is three thousand lines of Blade over
     * a dozen analytics queries; rendering it per test exhausts the worker
     * before the suite finishes, and every test here asks about the same
     * response anyway.
     */
    private static ?string $rendered = null;

    private function page(): string
    {
        if (self::$rendered !== null) {
            return self::$rendered;
        }

        $user = User::factory()->create();

        $link = Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => 'skinguard' . $user->id,
            'title'     => 'Skin Guard',
            'is_active' => true,
        ]);

        $resp = $this->actingAs($user)->followingRedirects()->get(route('user.links.show', $link));
        $resp->assertOk();

        return self::$rendered = $resp->getContent();
    }

    /**
     * Only THIS page's own <style> blocks.
     *
     * The document also carries the layout's stylesheets -- the global search
     * modal, the theme tokens -- and searching all of them for a hover lift
     * finds someone else's and reports a page that is in fact clean. A block
     * is this page's if it declares one of the classes this page invented.
     */
    private function localCss(string $html): string
    {
        preg_match_all('/<style>(.*?)<\/style>/s', $html, $m);

        $mine = array_filter($m[1], static fn (string $css): bool => str_contains($css, '.stat-tile')
            || str_contains($css, '.section-card')
            || str_contains($css, '.perf-coach'));

        $this->assertNotEmpty($mine, "The link page's own stylesheet did not render.");

        return implode("\n", $mine);
    }

    public function test_the_page_still_renders_its_numbers(): void
    {
        $html = $this->page();

        foreach (['Total clicks', 'Unique visitors', 'Sessions', 'Clicks Over Time'] as $needle) {
            $this->assertStringContainsString($needle, $html, "{$needle} vanished from the link page.");
        }
    }

    public function test_nothing_lifts_glows_or_prints_a_figure_in_a_gradient(): void
    {
        $css = $this->localCss($this->page());

        $retired = [
            'transform: translateY(-3px)'            => 'a stat tile lifting on hover',
            'transform: translateY(-2px)'            => 'a KPI card lifting on hover',
            'box-shadow: 0 6px 18px rgba(61,107,255' => 'a coloured glow under a filter chip',
            'box-shadow: 0 8px 22px var(--sc-glow'   => 'a glowing section icon',
            'box-shadow: 0 8px 20px var(--tile-glow' => 'a glowing stat icon',
            'background: linear-gradient(135deg, #3d6bff, #5c83ff)' => 'a gradient filter chip',
            '-webkit-background-clip: text'          => 'a figure printed in a gradient',
            'box-shadow: 0 0 12px var(--bar-glow'    => 'a glowing bar',
        ];

        foreach ($retired as $needle => $what) {
            $this->assertStringNotContainsString($needle, $css, "The link page still renders {$what}.");
        }
    }

    public function test_the_section_accent_rainbow_is_no_longer_consumed(): void
    {
        $css = $this->localCss($this->page());

        // The inline --sc-accent / --sc-glow declarations stay on the markup so
        // the diff is small, but nothing may read them back out: twelve
        // sections in twelve colours encoded nothing about their subjects.
        foreach (['var(--sc-accent', 'var(--sc-glow', 'var(--sc-border', 'var(--tile-accent'] as $token) {
            $this->assertStringNotContainsString(
                $token,
                $css,
                "The link page still paints from {$token}, so the per-section colour is back."
            );
        }
    }

    public function test_severity_colour_survives_because_it_carries_meaning(): void
    {
        $html = $this->page();

        // Quieting the coach must not flatten critical/warning/tip/win into one
        // grey. The colour moved onto the icon; it did not go away.
        $this->assertStringContainsString('pc-insight-icon', $html);
    }

    public function test_the_page_hero_carries_the_ribbon_and_casts_no_shadow(): void
    {
        $html = $this->page();

        // The hero is the first card on every user page, so it is where the one
        // gradient moment lives. Both layers, not just the ribbon: the lattice
        // is what ties the card to the page behind it.
        $this->assertStringContainsString('page-hero', $html);
        $this->assertStringContainsString('cribbon-grid', $html);
        $this->assertStringContainsString('cribbon-copy', $html);

        // And it was the last card on the dashboard still casting one.
        if (preg_match('/\.page-hero\s*\{(.*?)\}/s', $html, $m)) {
            $this->assertStringNotContainsString(
                'box-shadow',
                $m[1],
                'The page hero is casting a shadow again.'
            );
        } else {
            $this->fail('The .page-hero rule did not render.');
        }
    }

    public function test_the_local_stylesheet_closes_every_comment_it_opens(): void
    {
        $css = $this->localCss($this->page());

        $this->assertSame(substr_count($css, '/*'), substr_count($css, '*/'), 'Unbalanced CSS comments — a rule is being swallowed.');
        $this->assertSame(substr_count($css, '{'), substr_count($css, '}'), 'Unbalanced CSS braces — a rule is being swallowed.');
    }
}
