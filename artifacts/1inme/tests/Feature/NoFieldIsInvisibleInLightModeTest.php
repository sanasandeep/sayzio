<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A form control must not take its colours from a hardcoded white
 * literal.
 *
 * rgba(255,255,255,0.04) as a background and rgba(255,255,255,0.10) as
 * a border are the DARK theme's token values. Pasted inline they stop
 * being theme-aware, and in light mode the control renders transparent
 * white on a white card with a 10%-white border: there is nothing on
 * screen to tell anyone they can type there until they click into it
 * and it takes focus.
 *
 * The tokens resolve to #ffffff on #e3e0da in light mode and to those
 * same literals in dark, which is why the fix was a substitution
 * rather than a redesign.
 *
 * This is a source scan rather than a rendered-page check on purpose.
 * Rendering drags in every partial the layout pulls, which is a much
 * larger claim than the one worth making, and an earlier version of
 * this check failed on a shared widget that was never broken.
 */
class NoFieldIsInvisibleInLightModeTest extends TestCase
{
    /**
     * Strip balanced var(...) groups.
     *
     * var(--bg-input, rgba(255,255,255,0.05)) is correct: the literal
     * is an unreachable fallback behind a token defined in both
     * themes. Without this, every such control reads as a violation.
     */
    private function withoutCssVarFallbacks(string $css): string
    {
        $out = '';
        $i   = 0;
        $len = strlen($css);

        while ($i < $len) {
            if (substr($css, $i, 4) === 'var(') {
                $depth = 1;
                $i += 4;
                while ($i < $len && $depth > 0) {
                    if ($css[$i] === '(') { $depth++; }
                    if ($css[$i] === ')') { $depth--; }
                    $i++;
                }
                continue;
            }

            $out .= $css[$i];
            $i++;
        }

        return $out;
    }

    public function test_no_form_control_in_the_app_hardcodes_the_dark_palette(): void
    {
        $root = resource_path('views/user');

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $offenders = [];
        $scanned = 0;

        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $rel = str_replace($root . '/', '', $file->getPathname());

            // No carve-outs. An exclusion list was drafted for the
            // deliberately-dark auth and security pages, then dropped:
            // none of them actually violate this, so the list protected
            // nothing and would only have hidden the next one.
            $scanned++;
            $source = file_get_contents($file->getPathname());

            // [^>]* stops at the FIRST '>', and a Blade expression inside an
        // attribute contains one: value="{{ $tier->name }}". The tag then
        // truncates before its style attribute, the scan sees a control
        // with no style, and passes it. That is how this guard reported
        // "0 offenders across 579 views" while ten invisible controls sat
        // in three files. Allowing '>' inside quoted attribute values is
        // the fix.
        preg_match_all('~<(?:input|textarea|select)\b(?:[^>"]|"[^"]*")*>~s', $source, $m);

            foreach ($m[0] as $tag) {
                // Only the style attribute. A literal in `placeholder`
                // is example text shown to the user -- the colour
                // picker does exactly that -- and rewriting it would
                // change what is on screen.
                if (!preg_match('~style="([^"]*)"~', $tag, $sm)) {
                    continue;
                }

                if (str_contains($this->withoutCssVarFallbacks($sm[1]), 'rgba(255,255,255')) {
                    $offenders[] = $rel . ' :: ' . mb_substr(preg_replace('~\s+~', ' ', $tag), 0, 100);
                }
            }
        }

        $this->assertGreaterThan(500, $scanned, 'the scan found almost no views, so it is not actually checking anything');

        $this->assertSame([], $offenders, sprintf(
            "%d form control(s) style themselves for dark mode only and are invisible in light mode:\n  %s",
            count($offenders),
            implode("\n  ", $offenders)
        ));
    }

    /**
     * `color-scheme` in a style attribute can never be themed.
     *
     * It decides which variant the BROWSER draws for native widgets --
     * the date picker panel, the select option list, the scrollbar --
     * and the app already answers it correctly per theme: `:root` says
     * dark and `html.light-mode` says light. A declaration in a
     * stylesheet can sit under `html.light-mode` and be corrected.
     * An inline one outranks every selector and cannot be, so the
     * native picker stays dark on a light page with no rule able to
     * reach it.
     *
     * This found exactly one: a datetime-local input in the contacts
     * follow-up row. It slipped past the guard above because its
     * colours are all var() tokens and perfectly correct -- the only
     * wrong thing about it was a property that guard does not look at.
     *
     * Whole view tree on purpose, not just views/user. The
     * deliberately-dark standalone pages (maintenance, rate-limited,
     * the auth screens) set color-scheme in a <style> block, which is
     * fine and is what this leaves alone.
     */
    public function test_nothing_sets_color_scheme_in_a_style_attribute(): void
    {
        $root  = resource_path('views');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        $offenders = [];
        $scanned   = 0;

        foreach ($files as $file) {
            if (!$file->isFile() || !str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $scanned++;
            $source = file_get_contents($file->getPathname());

            // Same tag regex as above, and for the same reason: [^>]*
            // truncates at the '>' inside {{ $x->y }} and silently
            // passes the tag. Any element, not only form controls --
            // color-scheme inherits, so it can be set on a wrapper.
            preg_match_all('~<[a-zA-Z][^\s>]*(?:[^>"]|"[^"]*")*>~s', $source, $m);

            foreach ($m[0] as $tag) {
                if (!preg_match('~style="([^"]*)"~', $tag, $sm)) {
                    continue;
                }

                if (preg_match('~(^|[;\s])color-scheme\s*:~', $sm[1])) {
                    $offenders[] = str_replace($root . '/', '', $file->getPathname())
                        . ' :: ' . mb_substr(preg_replace('~\s+~', ' ', $tag), 0, 120);
                }
            }
        }

        $this->assertGreaterThan(500, $scanned, 'the scan found almost no views, so it is not actually checking anything');

        $this->assertSame([], $offenders, sprintf(
            "%d element(s) set color-scheme inline, where no theme rule can override it:\n  %s",
            count($offenders),
            implode("\n  ", $offenders)
        ));
    }
}
