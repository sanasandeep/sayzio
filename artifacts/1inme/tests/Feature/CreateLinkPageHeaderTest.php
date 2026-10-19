<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The Create Link page wears the shared page header, and its own styles are
 * scoped so they actually apply.
 *
 * Two things are guarded here, and the second is the one that cost time.
 *
 * create-premium-style.blade.php already carries, at (0,2,0) and (0,2,2):
 *
 *     .create-premium .cl-card { border:0; background:transparent; padding:0 }
 *     .create-premium .cl-bulk { border-top:1px solid ...; padding-top:20px }
 *     .create-premium .cl-bulk summary::before { content:'+' }
 *
 * The first version of the bulk-tools card was written as bare `.cl-bulk`
 * (0,1,0) and lost all three: no border, no surface, a stray top divider and a
 * glyph in front of the icon. Nothing failed -- the markup said "card" and the
 * page drew a rule. It was only caught by looking at the rendered page.
 *
 * So any rule in this page's own stylesheet that touches a class the partial
 * also claims has to carry the .create-premium scope, or it is decoration.
 */
class CreateLinkPageHeaderTest extends TestCase
{
    private const VIEW = 'views/user/links/create.blade.php';

    private function source(): string
    {
        return file_get_contents(resource_path(self::VIEW));
    }

    public function test_the_page_uses_the_shared_page_hero(): void
    {
        $this->assertStringContainsString(
            "@include('user.partials.page-hero'",
            $this->source(),
            'Create Link no longer includes the shared page hero, so it has drifted back to a one-off header'
        );
    }

    /**
     * The chips say "N link types" and "N categories". Both have to be counted
     * from the catalogue, never typed in, or they go stale the first time a
     * link type is added and nobody notices.
     */
    public function test_the_header_counts_come_from_the_catalogue(): void
    {
        $src = $this->source();

        $this->assertStringContainsString('LinkTypeCategories::categories()', $src);
        $this->assertStringContainsString('$clTypeCount', $src);
        $this->assertMatchesRegularExpression(
            '/\$clTypeCount\s*=\s*array_sum/',
            $src,
            'the link-type chip is not being counted from the catalogue'
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'text'\s*=>\s*'\d+ link types'/",
            $src,
            'the link-type chip has a hardcoded number in it'
        );
    }

    /**
     * Every rule for a class that create-premium-style also styles must be
     * scoped under .create-premium, or it silently loses the cascade.
     */
    public function test_page_styles_are_scoped_so_they_win_the_cascade(): void
    {
        $css = $this->ownStyleBlock();

        // Classes the partial claims at (0,2,x) and this page restyles.
        $contested = ['cl-bulk', 'cl-card', 'cl-steps', 'cl-ask', 'cl-primary-grid', 'cl-expanded-grid'];

        $unscoped = [];
        foreach (preg_split('/(?<=\})/', $css) as $chunk) {
            $selector = trim(explode('{', $chunk)[0]);
            if ($selector === '' || str_starts_with($selector, '@') || str_starts_with($selector, '/*')) {
                continue;
            }
            foreach ($contested as $class) {
                if (str_contains($selector, '.'.$class) && ! str_contains($selector, '.create-premium')) {
                    $unscoped[] = $selector;
                    break;
                }
            }
        }

        sort($unscoped);
        $this->assertSame([], $unscoped, 'these rules are not scoped under .create-premium, so create-premium-style beats them on specificity and they do nothing: '.implode(' | ', $unscoped));
    }

    /**
     * The page's own stylesheet must come AFTER the partial it overrides.
     *
     * Scoping was necessary and not sufficient. Both this file and
     * create-premium-style declare .create-premium .cl-bulk at (0,2,0), and at
     * equal specificity the later declaration wins. The block sat ABOVE the
     * @include, so in production the bulk card lost its border, its surface
     * and its padding and rendered as a bare divider -- while every
     * specificity assertion in this class passed.
     *
     * It survived a browser check too: the check appended a <style> to
     * document.body, which is after everything, so the probe answered a
     * question the real page was not being asked.
     */
    public function test_the_page_stylesheet_comes_after_the_partial_it_overrides(): void
    {
        $src = $this->source();

        $include = strpos($src, "@include('user.links.partials.create-premium-style')");
        $this->assertNotFalse($include, 'create-premium-style is no longer included; this guard needs rewriting');

        $ownBlock = strrpos($src, '<style>');
        $this->assertNotFalse($ownBlock, "no <style> block found in the Create Link view");

        $this->assertGreaterThan(
            $include,
            $ownBlock,
            "this page's <style> block sits before the create-premium-style include. Both declare the same selectors at the same specificity, so the include wins and these rules do nothing."
        );
    }

    /** This page's own <style> block — the last one, after the minified legacy block. */
    private function ownStyleBlock(): string
    {
        preg_match_all('#<style>([\s\S]*?)</style>#', $this->source(), $m);
        $this->assertNotEmpty($m[1], 'no <style> block found in the Create Link view');

        $own = end($m[1]);

        return preg_replace('#/\*[\s\S]*?\*/#', '', $own);
    }
}
