<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A Tailwind arbitrary-value class that was never compiled does nothing.
 *
 * It does not warn, it does not fall back, and it does not look wrong in the
 * Blade file -- it simply has no rule behind it, so the element renders as if
 * the class were not there.
 *
 * Found the hard way. Reserving a slot in the coin card with
 * `min-h-[1.1rem]` produced a slot of height 0, because the stylesheet is
 * built ahead of time and `1.1rem` had never appeared in any scanned file.
 * The card whose slot was empty came up 16px short and its "Buy" button sat
 * above every other button in the row. Nothing in the page or the build said
 * why; `min-h-[1.75rem]`, one line away in the same file, worked perfectly,
 * because some earlier markup happened to use it.
 *
 * So the rule this checks is: every arbitrary-value class written in these
 * views has a matching rule in the CSS that actually ships.
 *
 * Scoped to the pricing views, which is where the defect was. Widening it to
 * every marketing view is worth doing and is its own piece of work -- a first
 * pass over the homepage turned up candidates that need checking one at a
 * time, and dropping them in here would arrive as a wall of failures and get
 * the whole guard switched off.
 */
class MarketingArbitraryClassesAreCompiledTest extends TestCase
{
    /** Every stylesheet the marketing pages actually load. */
    private function shippedCss(): string
    {
        $css = '';

        foreach (array_merge(
            glob(public_path('build/assets/*.css')) ?: [],
            glob(public_path('css/*.css')) ?: [],
        ) as $file) {
            $css .= (string) file_get_contents($file);
        }

        return $css;
    }

    /** @return list<string> */
    private function views(): array
    {
        return glob(resource_path('views/public/pricing/*.blade.php')) ?: [];
    }

    /**
     * Tailwind escapes the characters that are not valid in a CSS identifier,
     * so `min-h-[1.75rem]` is written `.min-h-\[1\.75rem\]` in the sheet.
     */
    private function asSelector(string $class): string
    {
        return '.' . preg_replace('/([\[\].\/%#(),:])/', '\\\\$1', $class);
    }

    /**
     * Arbitrary-value classes written in one view.
     *
     * @return array<string,int> class => the line it first appears on
     */
    private function arbitraryClasses(string $path): array
    {
        $source = (string) file_get_contents($path);
        $found = [];

        preg_match_all('/class="([^"]*)"/s', $source, $attrs, PREG_OFFSET_CAPTURE);

        foreach ($attrs[1] as [$attr, $offset]) {
            // A Blade expression inside the attribute holds its classes in
            // string literals: `{{ $x ? 'min-h-[1.75rem] mb-2' : '' }}`.
            // Pull those out, then drop the expression, so the operators and
            // variable names around them are not mistaken for classes.
            $literals = '';
            if (preg_match_all('/\{\{(.*?)\}\}/s', $attr, $exprs)) {
                foreach ($exprs[1] as $expr) {
                    if (preg_match_all("/'([^']*)'/", $expr, $strings)) {
                        $literals .= ' ' . implode(' ', $strings[1]);
                    }
                }
            }
            $plain = preg_replace('/\{\{.*?\}\}/s', ' ', $attr) ?? $attr;

            foreach (preg_split('/\s+/', $plain . ' ' . $literals) ?: [] as $token) {
                $token = ltrim(trim($token, "'\""), '!');

                // Only arbitrary values: something in square brackets.
                if (! preg_match('/^[a-z0-9:\/_-]*\[[^\]]+\][a-z0-9\/_-]*$/i', $token)) {
                    continue;
                }

                $found[$token] ??= substr_count(substr($source, 0, $offset), "\n") + 1;
            }
        }

        return $found;
    }

    public function test_every_arbitrary_class_on_the_pricing_views_has_a_rule(): void
    {
        $css = $this->shippedCss();
        $dead = [];

        foreach ($this->views() as $path) {
            $short = str_replace(resource_path('views/'), '', $path);

            foreach ($this->arbitraryClasses($path) as $class => $line) {
                // The whole token, variants included: `hover:bg-white/[0.10]`
                // compiles to `.hover\:bg-white\/\[0\.10\]:hover`, and looking
                // for the bare `.bg-white\/[0.10]` part of it reports a rule
                // that is present as missing.
                if (! str_contains($css, $this->asSelector($class))) {
                    $dead[] = "{$short}:{$line}  {$class}";
                }
            }
        }

        $this->assertSame([], $dead, sprintf(
            "These arbitrary-value classes have no rule in the CSS that ships.\n\n"
            . "They are silent: the element renders as though the class were absent.\n"
            . "A reserved-height slot written this way is a slot of height zero, and\n"
            . "the card it was meant to keep in line quietly falls out of line.\n\n"
            . "Either use a value that is already compiled, or move the rule into the\n"
            . "page's own stylesheet where it does not depend on the Tailwind build.\n\n"
            . "%d found:\n  %s",
            count($dead),
            implode("\n  ", $dead)
        ));
    }

    /**
     * And the scan has to be finding classes to judge.
     *
     * The assertion above passes against an empty set, and an empty set is
     * what a changed directory layout or a broken regex produces.
     */
    public function test_the_scan_finds_arbitrary_classes_to_judge(): void
    {
        $all = [];

        foreach ($this->views() as $path) {
            $all += $this->arbitraryClasses($path);
        }

        // Named, not counted. `min-h-[3.75rem]` is the clamp that holds the
        // plan and coin description boxes to one height; if the scan stops
        // seeing it, the scan is not reading these views any more.
        $this->assertArrayHasKey(
            'min-h-[3.75rem]',
            $all,
            'the scan can no longer see min-h-[3.75rem] in the pricing views, so it is not reading them'
        );
    }
}
