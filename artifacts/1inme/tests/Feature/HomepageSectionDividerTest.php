<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every full-bleed homepage band declares how it separates from the one above.
 *
 * The first version of this test kept an OPTED_OUT list: an id, and a prose
 * reason of the form "sits on its own tinted ground". Nothing checked the
 * reason. And the light-mode sheet -- public/partials/surfaces.blade.php,
 * a file none of this was written next to -- flattens every section wash on
 * the page:
 *
 *     :is(.ai-zone-wash,.aisx-grid-bg,.aud-wash,.lt-wash){...transparent}
 *     .-z-10[style*="rgba(61,107,255"]{background:transparent !important}
 *
 * so the bands excused from carrying a hairline because they "announce
 * themselves by changing colour" were sitting on the same white as everything
 * else. Measured on the live page: seven consecutive bands with no separator
 * of any kind, from the top of the AI zone to the social-proof band -- just
 * under 8,000px of unbroken sheet, with this test green the whole time.
 *
 * A prose claim about how something looks cannot be a guard. So the exemption
 * is now `sec-ground`, a class that PAINTS the ground rather than asserting
 * one, and every full-bleed band carries exactly one of:
 *
 *     sec-ground -- brings a ground of its own, in both themes; no hairline
 *     sec-rule   -- sits on the page's ground; carries the hairline
 *
 * A band cannot claim a ground it does not have, because claiming it is what
 * draws it.
 *
 * Asserting on the Blade sources rather than the rendered page on purpose:
 * the homepage's lower half is fetched after load by JS, so a server-side GET
 * returns the shell without any of the bands this is about. Seven fragments
 * can be selected from the admin panel and every one of them is checked --
 * the previous version read only the default one, so the other six had no
 * dividers anywhere and nothing said so.
 */
class HomepageSectionDividerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Structural exceptions, and there are only two kinds now: a band with
     * nothing above it to divide from, and an element that is not a band.
     *
     * "It has its own background" is deliberately NOT expressible here any
     * more -- that is what sec-ground is for, and sec-ground has to paint.
     */
    private const NOTHING_ABOVE_IT = [
        'hero' => 'the first band on the page',
    ];

    /**
     * Every view that contributes a full-bleed band to a homepage, found the
     * way the page finds them: from the shell and each of the seven selectable
     * fragments, following @include down.
     *
     * Globbing directories instead was the other half of the old test's
     * problem, in both directions. It read only `deferred-sections.blade.php`,
     * so the six other fragments an admin can switch to had no dividers
     * anywhere and nothing said so; and it never read `public/partials/`, where
     * the homepage's compare band actually lives -- the opt-out list still
     * named `compare-legacy`, an id that stopped existing when that partial
     * replaced it, and the entry had been matching nothing for months.
     *
     * A glob of `public/partials/` instead would swing too far the other way:
     * that directory also holds bands for the pricing and features pages,
     * which are not this page's problem and would only invite an opt-out list
     * to grow back.
     */
    private function homepageBandViews(): array
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
            $source = (string) file_get_contents($path);

            // @include('a.b'), @includeIf('a.b'), @includeWhen($c, 'a.b'),
            // @includeFirst([...]) -- every literal view name in the file.
            preg_match_all(
                '/@include(?:If|When|Unless|First)?\s*\(\s*(?:[^)\'"]*?,\s*)?([\'"])([a-z0-9_.\-]+)\1/i',
                $source,
                $m
            );

            foreach ($m[2] as $view) {
                $queue[] = resource_path('views/' . str_replace('.', '/', $view) . '.blade.php');
            }
        }

        $paths = array_keys($seen);
        sort($paths);

        return $paths;
    }

    /**
     * The crawl above is the test's own input. A typo in the @include pattern
     * would shrink it to the roots and every assertion here would pass by
     * having nothing to check -- which is the shape of the bug this file was
     * rewritten for, so it gets its own guard.
     */
    public function test_the_crawl_reaches_the_partials_that_hold_bands(): void
    {
        $found = array_map(
            fn ($p) => str_replace(resource_path('views/'), '', $p),
            $this->homepageBandViews()
        );

        foreach ([
            'home/partials/ai-suite.blade.php',
            'home/partials/zio-hub.blade.php',
            'home/partials/pricing.blade.php',
            'home/partials/notifications.blade.php',
            'public/partials/marketing-trust-band.blade.php',
            'public/partials/_compare.blade.php',
        ] as $expected) {
            $this->assertContains($expected, $found, "the @include crawl never reached {$expected}");
        }

        $this->assertGreaterThan(30, count($found), 'the crawl collapsed; it is not reading the page any more');
    }

    /**
     * A band is a <section> that spans the page. Recognised by its vertical
     * rhythm: any py-/pt- step from 12 up, which is what every band on the
     * page uses and no card inside one does.
     *
     * The old matcher wanted `py-(16|20|24)` AND Tailwind's `relative`, and
     * two live bands walked straight through it -- the trust band is
     * `py-14 sm:py-20`, and the Zio hub band is `pt-24 ... pb-20` with no
     * `relative` at all.
     *
     * @return list<array{0:string,1:string,2:int}> [tag, classes, line]
     */
    private function bandsIn(string $source): array
    {
        $bands = [];

        preg_match_all('/<section\b[^>]*>/i', $source, $tags, PREG_OFFSET_CAPTURE);

        foreach ($tags[0] as [$tag, $offset]) {
            // Single or double quotes: the old pattern accepted only double,
            // so one @class([...]) or a single-quoted list would have been
            // invisible rather than reported.
            if (! preg_match('/\bclass=(["\'])(.*?)\1/is', $tag, $c)) {
                continue;
            }

            $classes = $c[2];

            if (! preg_match('/(?<![\w:-])(?:py|pt)-(1[2-9]|[2-9]\d)\b/', $classes)) {
                continue;
            }

            $line = substr_count(substr($source, 0, $offset), "\n") + 1;
            $bands[] = [$tag, $classes, $line];
        }

        return $bands;
    }

    /**
     * Every @include of $viewPath found anywhere in the crawled set, with the
     * arguments it passes.
     *
     * @return array<string,string> caller (short path + line) => argument text
     */
    private function includesOf(string $viewPath): array
    {
        $view = str_replace('/', '.', trim(
            str_replace([resource_path('views/'), '.blade.php'], '', $viewPath),
            '/'
        ));

        $callers = [];

        foreach ($this->homepageBandViews() as $path) {
            $source = (string) file_get_contents($path);

            $pattern = '/@include(?:If|When|Unless)?\s*\(\s*(?:[^)\'"]*?,\s*)?[\'"]'
                . preg_quote($view, '/') . '[\'"](.*?)\)\s*$/ims';

            if (! preg_match_all($pattern, $source, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
                continue;
            }

            foreach ($m as $hit) {
                $line = substr_count(substr($source, 0, $hit[0][1]), "\n") + 1;
                $short = str_replace(resource_path('views/'), '', $path);
                $callers["{$short}:{$line}"] = $hit[1][0];
            }
        }

        return $callers;
    }

    public function test_every_full_bleed_band_declares_a_rule_or_a_ground(): void
    {
        $undeclared = [];

        foreach ($this->homepageBandViews() as $path) {
            $short = str_replace(resource_path('views/'), '', $path);
            $source = (string) file_get_contents($path);

            foreach ($this->bandsIn($source) as [$tag, $classes, $line]) {
                $hasRule = (bool) preg_match('/(?<![\w-])sec-rule(?![\w-])/', $classes);
                $hasGround = (bool) preg_match('/(?<![\w-])sec-ground(?![\w-])/', $classes);

                if ($hasRule || $hasGround) {
                    continue;
                }

                preg_match('/\bid=(["\'])(.*?)\1/is', $tag, $idMatch);
                $id = $idMatch[2] ?? '';

                if ($id !== '' && array_key_exists($id, self::NOTHING_ABOVE_IT)) {
                    continue;
                }

                // A shared partial can take its declaration from the caller:
                // `class="{{ $sectionClass ?? '' }} py-20 ..."`. The compare
                // band does exactly this, because it also renders on /pricing
                // and the /compare pages, where the homepage's hairline is not
                // defined. Then the contract is on the call sites instead, and
                // all of them have to honour it.
                if (str_contains($classes, '{{')) {
                    $callers = $this->includesOf($path);

                    $this->assertNotSame([], $callers, "{$short}:{$line} takes its class from a caller, but nothing includes it");

                    foreach ($callers as $caller => $args) {
                        if (! preg_match('/(?<![\w-])sec-(rule|ground)(?![\w-])/', $args)) {
                            $undeclared[] = sprintf(
                                '%s:%d  id="%s"  (class comes from the caller; %s passes no sec-rule/sec-ground)',
                                $short, $line, $id !== '' ? $id : '(none)', $caller
                            );
                        }
                    }

                    continue;
                }

                $undeclared[] = sprintf('%s:%d  id="%s"', $short, $line, $id !== '' ? $id : '(none)');
            }
        }

        $this->assertSame([], $undeclared, sprintf(
            "These homepage bands say nothing about how they separate from the band above.\n\n"
            . "Add ONE of:\n"
            . "  sec-rule    the band sits on the page's ground and carries the hairline\n"
            . "  sec-ground  the band paints a ground of its own, in BOTH themes\n\n"
            . "There is no third option and no opt-out list. If the band has a background,\n"
            . "sec-ground is how it says so -- and sec-ground is what draws it, so the claim\n"
            . "cannot drift away from the page the way the old prose reasons did.\n\n%d found:\n  %s",
            count($undeclared),
            implode("\n  ", $undeclared)
        ));
    }

    /** A band declaring both is a band whose author has not decided. */
    public function test_no_band_claims_both_a_rule_and_a_ground(): void
    {
        $both = [];

        foreach ($this->homepageBandViews() as $path) {
            $short = str_replace(resource_path('views/'), '', $path);

            foreach ($this->bandsIn((string) file_get_contents($path)) as [, $classes, $line]) {
                if (preg_match('/(?<![\w-])sec-rule(?![\w-])/', $classes)
                    && preg_match('/(?<![\w-])sec-ground(?![\w-])/', $classes)) {
                    $both[] = "{$short}:{$line}";
                }
            }
        }

        $this->assertSame([], $both, 'a band carries the hairline or brings a ground, not both: ' . implode(', ', $both));
    }

    /**
     * Both classes have to actually draw something. If either rule is renamed
     * or dropped, every separator on the page goes at once and the tests above
     * would still pass -- which is exactly the failure mode that let the washes
     * disappear without anything going red.
     */
    public function test_both_separators_are_styled_in_both_themes(): void
    {
        $home = (string) file_get_contents(resource_path('views/home.blade.php'));

        $this->assertStringContainsString('.sec-rule::before', $home, 'nothing draws the hairline');
        $this->assertMatchesRegularExpression(
            '/\.sec-rule::before\s*\{[^}]*content:/s',
            $home,
            'the ::before needs a content property or it never renders'
        );
        $this->assertStringContainsString(
            'html.light-mode .sec-rule::before',
            $home,
            'the hairline needs a light-mode colour; the dark one is invisible on white'
        );

        $this->assertMatchesRegularExpression(
            '/\.sec-ground\)?\s*\{[^}]*background-color:/s',
            $home,
            'sec-ground has to paint a ground; a class that only marks is the prose reason again'
        );
        $this->assertMatchesRegularExpression(
            '/light-mode\)?\s*:?w?h?e?r?e?\(?\.sec-ground\)?\s*\{[^}]*--sec-ground:/s',
            $home,
            'sec-ground needs a light-mode value; one ground for both themes is one of them wrong'
        );
    }

    /**
     * The separator belongs to the boundary, not the band. Where a grounded
     * band is followed by one on the page ground, the change of colour has
     * already marked that edge and the hairline under it is a second
     * separator for the same join.
     */
    public function test_a_ground_suppresses_the_hairline_directly_below_it(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.sec-ground\s*\+\s*\.sec-rule::before/',
            (string) file_get_contents(resource_path('views/home.blade.php')),
            'nothing stops a grounded band and the hairline below it from double-marking one boundary'
        );
    }

    /**
     * The AI zone is ~6,600px across six bands -- a quarter of the page. It was
     * excused from every divider on the grounds that it "reads as one band",
     * which is the claim this whole test exists to stop anyone making again.
     * Its ground separates the zone from the page; the bands inside it share
     * that ground with each other and still need rules.
     */
    public function test_the_bands_inside_the_ai_zone_carry_rules(): void
    {
        foreach (['ai-hero', 'ai-suite', 'ai-marketing-strategist', 'whatsapp-agent', 'ai-dashboard'] as $id) {
            $file = collect($this->homepageBandViews())
                ->first(fn ($p) => str_contains((string) file_get_contents($p), 'id="' . $id . '"'));

            $this->assertNotNull($file, "no view renders #{$id}");

            $source = (string) file_get_contents($file);
            preg_match('/<section\b[^>]*id="' . preg_quote($id, '/') . '"[^>]*>/i', $source, $m);

            $this->assertNotEmpty($m, "#{$id} is not a <section>");
            $this->assertMatchesRegularExpression(
                '/(?<![\w-])sec-rule(?![\w-])/',
                $m[0],
                "#{$id} sits inside the AI zone's ground with five siblings and needs a rule between them"
            );
        }
    }
}
