<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AssertsAgainstLargeSubjects;
use Tests\TestCase;

/**
 * Text inside a surface that stays dark while the page turns white.
 *
 * public/css/marketing-anim.css rewrites the light utilities to near-black
 * for light mode, with !important and no scope:
 *
 *     html.light-mode :is(.text-white,.text-gray-100..300,.text-slate-100..300)
 *         { color: #1f2937 !important; }
 *     html.light-mode .text-gray-400 { color: #4b5563 !important; }
 *
 * which is right for the 95% of the page that turns white with it, and wrong
 * inside anything that does not. `.card-lit` already had the fix -- and its
 * own comment says the problem "is a property of the SURFACE, not of any one
 * card" -- but the fix was scoped to `.card-lit`, which also paints a blue
 * gradient, so no product mock could take it without turning blue.
 *
 * Audited in Chromium by compositing the real ground behind every text node in
 * light mode: five mocks were rendering near-black on near-black -- the
 * notifications panel (the reported one, 7 nodes), the marketing strategist
 * card (14), the share DNS rows (6), the dialer channel labels (5) and the AI
 * Suite screen (1). The biolink phone in Features LOOKED broken by a coarser
 * check and was not: it has its own light-mode rules, and adding the class to
 * it turned the label on its white "Templates" pill white-on-white. That near
 * miss is why this test accepts "has its own light-mode handling" as an
 * answer, rather than demanding the class everywhere.
 *
 * So: a class that paints a dark, opaque ground has to do ONE of two things,
 * and both are facts about the code rather than claims about the page.
 */
class HomepageDarkSurfaceInkTest extends TestCase
{
    use RefreshDatabase;
    use AssertsAgainstLargeSubjects;

    /**
     * The stylesheet that defines the separator and lit-surface system.
     *
     * It moved out of home.blade.php when the marketing pages started using it
     * too, and three guards went red on the move rather than on anything being
     * wrong. They read the file the rules live in now, found by looking, so the
     * next move does not break them either.
     */
    private function sectionSurfaceCss(): string
    {
        $candidates = [
            resource_path('views/public/partials/section-surfaces.blade.php'),
            resource_path('views/home.blade.php'),
        ];

        $css = '';

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $css .= "
" . file_get_contents($path);
            }
        }

        $this->assertStringContainsString(
            '.sec-rule::before',
            $css,
            'the separator rules are in neither of the files this test knows about'
        );

        return $css;
    }

    /** Anything at or below this relative luminance is "dark" for our purposes. */
    private const DARK = 0.12;

    private function homepageViews(): array
    {
        $roots = array_merge(
            [resource_path('views/home.blade.php')],
            glob(resource_path('views/home/deferred-sections*.blade.php'))
        );

        $seen = [];
        $queue = $roots;

        while ($queue) {
            $path = array_shift($queue);

            if (! is_file($path) || isset($seen[$path])) {
                continue;
            }

            $seen[$path] = true;

            preg_match_all(
                '/@include(?:If|When|Unless|First)?\s*\(\s*(?:[^)\'"]*?,\s*)?([\'"])([a-z0-9_.\-]+)\1/i',
                (string) file_get_contents($path),
                $m
            );

            foreach ($m[2] as $view) {
                $queue[] = resource_path('views/' . str_replace('.', '/', $view) . '.blade.php');
            }
        }

        return array_keys($seen);
    }

    /** Relative luminance of a #rgb/#rrggbb/rgb() colour, or null if not opaque/parseable. */
    private function luminance(string $colour): ?float
    {
        $colour = trim($colour);

        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $colour, $m)) {
            $hex = $m[1];
            if (strlen($hex) === 3) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
            }
            $rgb = [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
        } elseif (preg_match('/^rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)\s*(?:[,\/]\s*([\d.]+)\s*)?\)$/i', $colour, $m)) {
            // Translucent fills sit on whatever is behind them; not our business.
            if (isset($m[4]) && (float) $m[4] < 0.9) {
                return null;
            }
            $rgb = [(float) $m[1], (float) $m[2], (float) $m[3]];
        } else {
            return null;
        }

        $f = function ($v) {
            $v /= 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };

        return 0.2126 * $f($rgb[0]) + 0.7152 * $f($rgb[1]) + 0.0722 * $f($rgb[2]);
    }

    /**
     * Component classes that declare a dark opaque ground, from the homepage's
     * own <style> blocks.
     *
     * Only single-class rules -- `.foo { background: #0a0a14 }` -- because that
     * is where a component says "I am a dark surface". A nested rule is
     * decorating a surface someone else already declared.
     *
     * @return array<string,string> class => "file:line  colour"
     */
    private function darkSurfaceClasses(): array
    {
        $found = [];

        foreach ($this->homepageViews() as $path) {
            $source = (string) file_get_contents($path);
            $short = str_replace(resource_path('views/'), '', $path);

            foreach (preg_split('/(?<=\})/', $source) as $chunk) {
                if (! preg_match('/(^|[\s>};])(\.[a-z][\w-]*)\s*\{([^}]*)\}\s*$/is', $chunk, $m)) {
                    continue;
                }

                // Rules already scoped to a theme are the handling, not the problem.
                $selectorStart = strrpos(substr($chunk, 0, strpos($chunk, '{')), "\n");
                $selectorLine = trim(substr($chunk, $selectorStart === false ? 0 : $selectorStart));

                if (str_contains($selectorLine, 'light-mode') || str_contains($selectorLine, ':not(.light-mode)')) {
                    continue;
                }

                $class = ltrim($m[2], '.');
                $body = $m[3];

                if (! preg_match_all('/background(?:-color)?\s*:\s*([^;!}]+)/i', $body, $bg)) {
                    continue;
                }

                foreach ($bg[1] as $value) {
                    $value = trim($value);

                    // A gradient counts when every opaque stop in it is dark --
                    // four of the five mocks the audit found paint their ground
                    // with one, and an earlier version of this test that only
                    // read flat colours passed on all four by never looking at
                    // them.
                    if (preg_match('/gradient\(/i', $value)) {
                        preg_match_all('/#[0-9a-f]{3,8}\b|rgba?\([^)]*\)/i', $value, $stops);

                        $lums = array_filter(
                            array_map(fn ($s) => $this->luminance($s), $stops[0]),
                            fn ($l) => $l !== null
                        );

                        if ($lums && max($lums) <= self::DARK) {
                            $found[$class] = "{$short}  {$value}";
                        }

                        continue;
                    }

                    if (! preg_match('/^(#[0-9a-f]{3,6}|rgba?\([^)]*\))$/i', $value)) {
                        continue;
                    }

                    $lum = $this->luminance($value);

                    if ($lum !== null && $lum <= self::DARK) {
                        $found[$class] = "{$short}  {$value}";
                    }
                }
            }
        }

        ksort($found);

        return $found;
    }

    /** Everything the browser loads that can restyle the homepage. */
    private function homepageStylesheets(): array
    {
        return array_merge(
            $this->homepageViews(),
            array_filter([
                public_path('css/marketing-anim.css'),
            ], 'is_file')
        );
    }

    /**
     * Every class that a light-mode rule forces to a DARK colour.
     *
     * Derived rather than listed, because there turned out to be two of these
     * sheets and they are nothing alike:
     *
     *   marketing-anim.css   the Tailwind light utilities -- .text-white and
     *                        the gray/slate 100-400 steps -- to #1f2937
     *   surfaces.blade.php   a hand-written list of component classes
     *                        (.ms-item-text, .dc-dialchan-label, .aisx-card-desc,
     *                        …) to var(--fs-ink-2), under a comment calling
     *                        itself a stopgap
     *
     * An earlier version of this test hardcoded the first sheet's utilities and
     * so found only the notifications panel -- one of the five mocks that were
     * actually broken. The other four are coloured by the second sheet.
     *
     * Both are right for the page they were written for and wrong inside a
     * surface that stays dark, and either one gaining an entry is a new way for
     * this bug to come back.
     *
     * @return array<string,string> class => which sheet forces it
     */
    private function classesForcedDark(): array
    {
        $forced = [];

        foreach ($this->homepageStylesheets() as $path) {
            $source = (string) file_get_contents($path);
            $short = str_replace([resource_path('views/'), public_path()], '', $path);

            preg_match_all(
                '/(html\.light-mode[^{}]*)\{([^}]*)\}/is',
                $source,
                $rules,
                PREG_SET_ORDER
            );

            foreach ($rules as [, $selector, $body]) {
                if (! preg_match('/(?:^|;)\s*color\s*:\s*([^;!}]+)/i', $body, $c)) {
                    continue;
                }

                $value = trim($c[1]);

                // var(--fs-ink-2) and friends resolve to the light theme's ink,
                // which is dark by definition -- that is what the token is.
                $isDark = str_contains($value, 'var(--fs-ink')
                    || (($lum = $this->luminance($value)) !== null && $lum <= 0.35);

                if (! $isDark) {
                    continue;
                }

                // A rule that repaints the surface light in the same breath is
                // not forcing dark ink onto a dark ground -- it is a component
                // handling light mode properly. `.av-screen` does exactly this:
                // `{ background: #F7F8FC; color: #0F172A }`.
                if (preg_match('/background(?:-color)?\s*:\s*([^;!}]+)/i', $body, $b)) {
                    $bgLum = $this->luminance(trim($b[1]));

                    if ($bgLum !== null && $bgLum > 0.35) {
                        continue;
                    }
                }

                // Only the LAST compound of each selector: that is the element
                // the rule colours. `html.light-mode .panel .label {color:…}`
                // says something about .label, nothing about .panel, and
                // treating both as forced-dark reported two mocks that were
                // fine.
                //
                // :is(a, b, c) in that position expands to each of a, b, c --
                // the surfaces sheet writes its whole component list that way.
                foreach (preg_split('/,(?![^(]*\))/', $selector) as $one) {
                    $one = trim($one);

                    if ($one === '') {
                        continue;
                    }

                    // Split on combinators, but never inside :is()/:where():
                    // the surfaces sheet writes its component list as
                    // `:is(.lt-chip,.lt-chip span,.ms-item-text,…)`, and a
                    // naive split on whitespace saw the space in `.lt-chip
                    // span`, counted three compounds and skipped the whole
                    // rule -- which lost .ms-item-text and .dc-dialchan-label,
                    // two of the five mocks this test exists for.
                    $compounds = preg_split('/\s*[\s>+~]\s*(?![^(]*\))/', $one);
                    $last = (string) end($compounds);

                    // Only UNSCOPED rules -- `html.light-mode .foo`, one step.
                    // Those are the sledgehammers: they hit .foo wherever it
                    // is, including inside a dark surface. A scoped rule like
                    // `html.light-mode .bz-follow .meta` is a component
                    // colouring its own children, and reading it as global
                    // matched every unrelated `.meta` on the page.
                    if (count($compounds) > 2) {
                        continue;
                    }

                    preg_match_all('/\.((?:[a-z][\w-]*)(?:\\\\\/\d+)?)/i', $last, $classes);

                    foreach ($classes[1] as $class) {
                        $class = str_replace('\\/', '/', $class);

                        if ($class === 'light-mode') {
                            continue;
                        }

                        $forced[$class] = $short;
                    }
                }
            }
        }

        ksort($forced);

        return $forced;
    }

    public function test_every_dark_surface_holding_rewritten_text_says_what_happens_to_it(): void
    {
        $css = implode("\n", array_map(
            fn ($p) => (string) file_get_contents($p),
            $this->homepageViews()
        ));

        $darkClasses = $this->darkSurfaceClasses();
        $forcedDark = $this->classesForcedDark();

        $this->assertNotEmpty($darkClasses, 'no dark surfaces found; the CSS scan has stopped working');
        $this->assertNotEmpty($forcedDark, 'no light-mode ink rules found; the CSS scan has stopped working');

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="utf-8" ?><body>'
            . $this->get('/')->assertOk()->getContent()
            . $this->get('/home/sections')->assertOk()->getContent()
            . '</body>'
        );
        libxml_clear_errors();

        $xpath = new \DOMXPath($doc);
        $unhandled = [];

        foreach ($darkClasses as $class => $where) {
            // Elements that ARE this dark surface.
            $hosts = $xpath->query(
                sprintf('//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $class)
            );

            foreach ($hosts as $host) {
                /** @var \DOMElement $host */
                // Does anything inside it take its colour from a utility the
                // light-mode sheet rewrites to near-black?
                $carriers = [];
                foreach ($forcedDark as $utility => $sheet) {
                    $hits = $xpath->query(
                        sprintf('.//*[contains(concat(" ", normalize-space(@class), " "), " %s ")]', $utility),
                        $host
                    );
                    if ($hits->length) {
                        $carriers[] = ".{$utility} x{$hits->length} ({$sheet})";
                    }
                }

                if (! $carriers) {
                    continue;
                }

                // Answered in the markup: this element or an ancestor is lit.
                $lit = false;
                for ($n = $host; $n instanceof \DOMElement; $n = $n->parentNode) {
                    $c = ' ' . preg_replace('/\s+/', ' ', trim($n->getAttribute('class'))) . ' ';
                    if (str_contains($c, ' surface-lit ') || str_contains($c, ' card-lit ')) {
                        $lit = true;
                        break;
                    }
                }

                // Or answered in the stylesheet: a light-mode rule rooted at
                // this class that sets a colour. "Has some light-mode rule" is
                // not enough -- a border tweak is not an answer about ink.
                $themed = (bool) preg_match(
                    '/html\.light-mode[^{,]*\.' . preg_quote($class, '/') . '(?![\w-])[^{]*\{[^}]*\bcolor\s*:/is',
                    $css
                );

                if ($lit || $themed) {
                    continue;
                }

                $unhandled[] = sprintf('.%s  (%s)  holds %s', $class, $where, implode(', ', $carriers));
                break;
            }
        }

        $this->assertSame([], $unhandled, sprintf(
            "These elements paint a dark, opaque ground AND hold text coloured by a utility\n"
            . "that public/css/marketing-anim.css rewrites to near-black in light mode. Nothing\n"
            . "says what should happen to it, so the default outcome is near-black on\n"
            . "near-black -- which is what the notifications panel was doing when it was\n"
            . "reported.\n\n"
            . "Either:\n"
            . "  - add `surface-lit` to the element, which keeps its ink light (and\n"
            . "    `surface-lit-keep` on any sub-panel that has a light fill of its own);\n"
            . "  - or give the class its own `html.light-mode` rules, the way .bb-phone and\n"
            . "    .cd-bar do -- that counts as handled and this test accepts it.\n\n%d found:\n  %s",
            count($unhandled),
            implode("\n  ", $unhandled)
        ));
    }

    /**
     * The mechanism itself. If the ink rule is renamed, narrowed to .card-lit
     * again, or stops covering the utilities the light-mode sheet rewrites,
     * every mock silently goes back to black-on-black and the test above still
     * passes -- it only checks that the class is present.
     */
    public function test_surface_lit_covers_what_the_light_mode_sheet_rewrites(): void
    {
        $home = $this->sectionSurfaceCss();

        $this->assertPatternFound(
            '/html\.light-mode\s*:is\([^)]*\.card-lit[^)]*\.surface-lit[^)]*\)[^{]*\{[^}]*color:\s*#fff\s*!important/s',
            $home,
            'the ink rule must serve both .card-lit and .surface-lit from one place; two copies is two places to forget'
        );

        // The utilities marketing-anim.css rewrites to near-black, which are
        // therefore the ones that need answering inside a dark surface.
        foreach (['text-white', 'text-gray-300', 'text-gray-400', 'text-slate-300', 'text-slate-400'] as $utility) {
            $this->assertPatternFound(
                '/html\.light-mode[^{]*\.surface-lit[^{]*' . preg_quote($utility, '/') . '(?![\w-])/s',
                $home,
                "surface-lit says nothing about .{$utility}, which the light-mode sheet rewrites to near-black"
            );
        }
    }

    /**
     * The escape has to be a :not() on the rule, not a rule that puts the
     * colour back. Once `color: #fff !important` has landed on a subtree,
     * `color: inherit !important` under it only inherits that white -- which is
     * exactly what the first attempt did, and why the share section's URL bar
     * went from three accent colours to one flat white.
     */
    public function test_the_escape_excludes_rather_than_repaints(): void
    {
        $home = $this->sectionSurfaceCss();

        // Both escapes have to be in the :not(), each with its descendants.
        // `.card-lit-cta` is there because adding `.surface-lit-keep *` raised
        // the ink rule by one class, past the rule that colours the CTA, and
        // the pricing card's button went white-on-white -- fixing one card's
        // ink broke another card's escape.
        foreach (['surface-lit-keep', 'card-lit-cta'] as $escape) {
            $this->assertPatternFound(
                '/:not\([^)]*\.' . preg_quote($escape, '/') . '\s*,[^)]*\.' . preg_quote($escape, '/') . '\s+\*/s',
                $home,
                "{$escape} must be excluded from the ink rule together with its descendants"
            );
        }

        $this->assertPatternAbsent(
            '/\.surface-lit-keep[^{]*\{\s*color:\s*inherit\s*!important/s',
            $home,
            'inherit !important under a landed !important inherits the white it was meant to escape'
        );
    }

    /**
     * The five mocks the audit actually found, by the text that was
     * unreadable. Named here because a rendered assertion is the only thing
     * that fails if someone removes the class from one of them and the class
     * happens to survive elsewhere in the file.
     */
    public function test_the_audited_mocks_still_carry_the_class(): void
    {
        $html = $this->get('/home/sections')->assertOk()->getContent();

        foreach ([
            'nf-panel' => 'the notifications feed (Priya started following you)',
            'ms-card' => 'the AI marketing strategist card',
            'cd-stage' => 'the custom-domain DNS rows',
            'dc-phone' => 'the dialer channel labels',
            'aisx-screen' => 'the AI Suite screen',
        ] as $class => $what) {
            $this->assertPatternFound(
                '/class="[^"]*(?:' . $class . '[^"]*surface-lit|surface-lit[^"]*' . $class . ')[^"]*"/',
                $html,
                "{$what} lost surface-lit; its text goes near-black on near-black in light mode"
            );
        }

        // And the one that must NOT have it: adding it turned the label on its
        // white "Templates" pill white-on-white.
        $this->assertPatternAbsent(
            '/class="bb-phone surface-lit"/',
            $html,
            'the biolink phone handles light mode itself; surface-lit blanks the labels on its white pills'
        );
    }
}
