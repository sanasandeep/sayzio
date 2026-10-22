<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * CSS printed on the page as text.
 *
 * sayzio.app/events rendered about sixty lines of stylesheet above the
 * hero, in public, because `</style>` in events-directory.blade.php
 * closed one rule early. Everything after it to `@endpush` was still
 * CSS, still inside the pushed stack, and now outside any style
 * element, so the browser laid it out as paragraphs.
 *
 * WHY A SCAN AND NOT A RENDER TEST
 *
 * Rendering /events needs a database, a map key and a page of fixtures.
 * The defect is textual and the text is checkable, so this reads the
 * views instead.
 *
 * THE EXCLUSIONS ARE THE WHOLE DIFFICULTY
 *
 * A naive "CSS outside <style>" scan is useless here: it flags every
 * `.then(function` chain in a script, every CSS-only partial that a
 * parent includes inside its own style block, and every rule quoted in
 * a Blade comment. Three successive refinements got from 47 files of
 * noise to one true positive:
 *
 *   1. only views that open a <style> of their own, which drops the
 *      partials that are CSS by design (report-styles, resume-styles,
 *      page-background/*);
 *   2. track <script> as well as <style>, which drops most JS;
 *   3. drop lines that are obviously JS and lines inside {{-- --}}.
 *
 * Each one was checked against the whole view tree, and the finished
 * scan flagged exactly the broken file and nothing else. The control
 * below keeps that honest.
 */
class NoStylesheetEscapesItsStyleTagTest extends TestCase
{
    /** A line that starts a CSS rule. */
    private const SELECTOR = '/^\s*(\.[A-Za-z][\w-]*|#[A-Za-z][\w-]*|html\.[\w.\\\\\/-]+|@media\b|@supports\b|:root)[^{;]*\{/';

    /** Shapes that look like a selector but are JavaScript. */
    private const JS = '/=>|\bfunction\b|\.then\(|\.catch\(|\.map\(|\.filter\(|toLocaleString/';

    public function test_no_stylesheet_escapes_its_style_tag(): void
    {
        $offenders = [];

        foreach ($this->viewsThatOpenAStyleTag() as $file => $source) {
            foreach ($this->escapedRules($source) as [$line, $text]) {
                $offenders[] = "{$file}:{$line}  {$text}";
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['CSS outside any <style>, so the browser will print it as text on the page:', ''],
            $offenders
        )));
    }

    public function test_the_scan_catches_the_shape_it_is_for(): void
    {
        // The control, and the exact shape that shipped: a style block
        // closed one rule early, the rest of the stylesheet left loose
        // inside the push.
        $broken = <<<'BLADE'
        @push('head')
        <style>
            .ev-card-img { width: 100%; }
        </style>

            .evdir-split { display: grid; }
            .evdir-map-panel { position: relative; }
        @endpush
        BLADE;

        $found = $this->escapedRules($broken);

        $this->assertCount(2, $found, 'the scan missed the defect it exists for, so it proves nothing');
        $this->assertStringContainsString('.evdir-split', $found[0][1]);
    }

    public function test_the_scan_does_not_flag_the_things_that_fooled_it_before(): void
    {
        // Each of these was a false positive on an earlier version, and
        // each would have made this test too noisy to keep.
        $fine = <<<'BLADE'
        <style>
            .real-rule { color: red; }
        </style>
        <script>
            fetch(url)
                .then(function (r) { return r.json(); })
                .catch(function () {});
            const s = n.toLocaleString(undefined, { style: 'currency' });
        </script>
        {{-- the old note mentioned .create-premium .cl-bulk{border-top} here --}}
        BLADE;

        $this->assertSame([], $this->escapedRules($fine));
    }

    /** @return array<string, string> relative path => source */
    private function viewsThatOpenAStyleTag(): array
    {
        $out = [];
        $root = resource_path('views');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($it as $f) {
            if (! $f->isFile() || ! str_ends_with($f->getFilename(), '.blade.php')) {
                continue;
            }
            $source = (string) file_get_contents($f->getPathname());

            // A partial that is nothing but CSS has no <style> of its
            // own; its parent supplies one. Those are correct and must
            // not be flagged.
            if (! str_contains($source, '<style')) {
                continue;
            }
            $out[str_replace($root . '/', '', $f->getPathname())] = $source;
        }

        ksort($out);

        $this->assertGreaterThan(50, count($out), 'the view walk found almost nothing, so this checked nothing');

        return $out;
    }

    /** @return array<int, array{0: int, 1: string}> [line number, text] */
    private function escapedRules(string $source): array
    {
        $out = [];
        $styleDepth = $scriptDepth = $commentDepth = 0;

        foreach (explode("\n", $source) as $i => $line) {
            $wasOutside = $styleDepth <= 0 && $scriptDepth <= 0 && $commentDepth <= 0;

            $commentDepth += substr_count($line, '{{--') - substr_count($line, '--}}');
            $styleDepth   += preg_match_all('/<style\b/', $line)  - substr_count($line, '</style>');
            $scriptDepth  += preg_match_all('/<script\b/', $line) - substr_count($line, '</script>');

            if ($wasOutside
                && preg_match(self::SELECTOR, $line)
                && ! preg_match(self::JS, $line)) {
                $out[] = [$i + 1, trim($line)];
            }
        }

        return $out;
    }
}
