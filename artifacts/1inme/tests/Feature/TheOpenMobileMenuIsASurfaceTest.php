<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The public site's mobile menu has a ground of its own.
 *
 * Reported with a screenshot: /pricing in Chrome, dark mode, narrow window.
 * The menu's ten links -- Product, Solutions, Events, Company, Pricing,
 * About, Contact, FAQs, Dashboard, Sign out -- were laid straight over the
 * hero, colliding with "Pricing that scales with you."
 *
 * Measured on the live page: the navbar bar computes to rgba(0,0,0,0), and
 * the menu panel carried only a border-top. The open menu had nothing behind
 * it at all.
 *
 * Not the backdrop-filter fallback, which is for browsers without the
 * property; this is Chrome, which has had it since 76. The bar is a 4%-white
 * glass that depends on blurring what is behind it, and over a flat
 * near-black page there is nothing to blur.
 *
 * The panel gets its own ground rather than borrowing the bar's, which is
 * right whatever the bar happens to be doing -- and that matters here,
 * because the bar measured fully transparent rather than the 4% its own rule
 * states and the reason for that is not yet established.
 */
class TheOpenMobileMenuIsASurfaceTest extends TestCase
{
    private function header(): string
    {
        return file_get_contents(resource_path('views/public/partials/header.blade.php'));
    }

    private function css(): string
    {
        return file_get_contents(public_path('css/marketing-anim.css'));
    }

    /** The panel is a named surface, not an unstyled div. */
    public function test_the_mobile_menu_panel_carries_its_own_class(): void
    {
        $this->assertStringContainsString(
            'mkt-mobile-menu',
            $this->header(),
            'the mobile menu panel has no class of its own, so it cannot be given a ground'
        );
    }

    /** And that class actually paints something, in both themes. */
    public function test_the_panel_class_paints_a_ground_in_both_themes(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.mkt-mobile-menu\s*\{[^}]*background-color\s*:/s',
            $css,
            'the mobile menu class exists but paints no background'
        );
        $this->assertMatchesRegularExpression(
            '/html\.light-mode\s+\.mkt-mobile-menu\s*\{[^}]*background-color\s*:/s',
            $css,
            'the mobile menu has no light-mode ground, so it inherits the dark one on a white page'
        );
    }

    /**
     * The bar is opaque while the menu is open.
     *
     * The glass bar is the right look at rest over a hero. It is the wrong
     * look wrapped around an open menu, where it is the only thing between
     * ten links and the page.
     */
    public function test_the_bar_is_told_when_the_menu_is_open(): void
    {
        $this->assertStringContainsString(
            "'is-menu-open': mobileOpen",
            $this->header(),
            'the bar is not bound to the menu state, so it cannot react to it'
        );

        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/\.mkt-navbar-bar\.is-menu-open\s*\{[^}]*background-color\s*:[^;]*!important/s',
            $css,
            'the open-menu bar background is not !important; measured on the live page, '
            . 'a plain rule lost to whatever currently zeroes the bar'
        );
    }

    /**
     * The control: the bar still has its glass look at rest.
     *
     * A "fix" that made the bar permanently opaque would pass every
     * assertion above and quietly flatten the header on every public page.
     */
    public function test_the_bar_is_still_glass_when_the_menu_is_closed(): void
    {
        $css = $this->css();

        preg_match('/\n\.mkt-navbar-bar\s*\{([^}]*)\}/s', $css, $m);
        $base = $m[1] ?? '';

        $this->assertNotSame('', $base, 'the base bar rule is gone');
        $this->assertMatchesRegularExpression(
            '/backdrop-filter\s*:\s*blur/',
            $base,
            'the bar at rest lost its blur, so the floating glass header is flattened everywhere'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/background-color\s*:[^;]*!important/',
            $base,
            'the bar at rest forces its background, which would override the stuck and open states'
        );
    }
}
