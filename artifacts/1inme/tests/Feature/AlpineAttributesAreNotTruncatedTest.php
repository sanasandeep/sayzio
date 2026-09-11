<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * No Blade view writes an apostrophe inside a single-quoted x-data attribute.
 *
 * Sana found the Marketing Settings screen showing no rows and no working
 * buttons. The cause was one character. The attribute was written as
 *
 *     x-data='{ ... resetTo(key, defaults) { ... title: 'Reset this section?'
 *
 * and the browser ends an attribute at the first matching quote, so it ended
 * at the apostrophe before `Reset`. Alpine received a truncated expression
 * with three unclosed braces, threw a SyntaxError, and initialised NOTHING on
 * the page: five repeaters rendered zero rows, every Add / Reset / Move /
 * Remove button did nothing, and the only sign was a console error nobody was
 * looking at.
 *
 * That is the property worth testing, rather than the one screen: the failure
 * is silent, it takes out an entire page rather than one control, and the
 * mistake is a single quote in a long attribute that reviewers read past. The
 * safe patterns are already in the codebase and stay passing here:
 *
 *     x-data='@json($state)'                    -- @json escapes apostrophes
 *     x-data='component(@json($state))'         -- behaviour lives in a script
 *
 * Both keep hand-written strings out of the attribute entirely.
 */
class AlpineAttributesAreNotTruncatedTest extends TestCase
{
    /** Every .blade.php under resources/views. */
    private function bladeFiles(): array
    {
        $dir = resource_path('views');
        $files = [];

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * The offset of the first apostrophe that sits INSIDE the expression,
     * or null when the attribute is clean.
     *
     * Finding "an apostrophe somewhere after x-data='" is not enough -- every
     * clean attribute is also followed, eventually, by an apostrophe
     * elsewhere in the file. What separates the two cases is DEPTH. The quote
     * that legitimately closes the attribute sits at brace/paren depth zero;
     * one that appears while a brace or paren is still open is inside the
     * expression, and that is the bug.
     *
     * Blade escapes apostrophes inside @json(...) and {{ ... }}, so both are
     * replaced with an inert placeholder first. Flagging those would make the
     * guard noisy, and a noisy guard gets deleted.
     */
    private function firstApostropheInsideExpression(string $source, int $from): ?int
    {
        // Placeholders keep offsets meaningless, so work on a copy and only
        // report whether a hazard exists, not where in the original file.
        $tail = substr($source, $from, 4000);
        $tail = (string) preg_replace('/@json\([^)]*\)/', 'JJJJ', $tail);
        $tail = (string) preg_replace('/\{\{.*?\}\}/s', 'EEEE', $tail);

        $depth = 0;
        $len = strlen($tail);

        for ($i = 0; $i < $len; $i++) {
            $c = $tail[$i];

            if ($c === '{' || $c === '(' || $c === '[') {
                $depth++;
            } elseif ($c === '}' || $c === ')' || $c === ']') {
                $depth--;
            } elseif ($c === "'") {
                // Depth zero: this is the quote that closes the attribute.
                // Anything deeper is a hand-written string inside it.
                return $depth > 0 ? $i : null;
            }
        }

        return null;
    }

    public function test_no_view_puts_an_apostrophe_inside_a_single_quoted_x_data(): void
    {
        $offenders = [];

        foreach ($this->bladeFiles() as $path) {
            $source = (string) file_get_contents($path);

            if (! preg_match_all("/x-data='/", $source, $m, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($m[0] as [$_, $offset]) {
                $start = $offset + strlen("x-data='");

                if ($this->firstApostropheInsideExpression($source, $start) === null) {
                    continue;
                }

                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $offenders[] = str_replace(resource_path('views') . '/', '', $path) . ':' . $line;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A single-quoted x-data contains a hand-written apostrophe, which ends the "
            . "attribute early and stops Alpine initialising the whole page silently.\n"
            . "Move the strings into a function in a <script> and pass data with @json:\n"
            . "    x-data='componentName(@json(\$state))'\n"
            . "Offending views:\n  " . implode("\n  ", $offenders)
        );
    }

    /**
     * The screen that was broken renders its component call, not an object
     * literal.
     *
     * The test above is the general guard. This one names the page, so that
     * if someone reverts it specifically the failure says which screen went
     * dark rather than only which rule was broken.
     */
    public function test_marketing_settings_uses_a_component_function(): void
    {
        $source = (string) file_get_contents(
            resource_path('views/admin/marketing-settings/index.blade.php')
        );

        $this->assertStringContainsString(
            "x-data='marketingSettings(@json(\$alpineState))'",
            $source,
            'Marketing Settings is back to an inline x-data object literal'
        );

        $this->assertStringContainsString(
            'function marketingSettings(state)',
            $source,
            'the marketingSettings component function is missing, so x-data will '
            . 'reference an undefined function and the page will render no rows'
        );
    }
}
