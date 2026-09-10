<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A Blade comment is not a safe place to write @-php.
 *
 * Blade's compiler extracts raw PHP blocks in `storeUncompiledBlocks()`,
 * which runs BEFORE comments are stripped. So a comment that merely mentions
 * the directive opens a real raw block, and everything from there to the next
 * closing directive -- anywhere in the file, however far down -- is taken as
 * PHP source and vanishes from the output.
 *
 * This is how it presented, which is worth recording because none of it looked
 * like a Blade problem:
 *
 *   - a comment was added to deferred-sections.blade.php describing the dead
 *     code it replaced, and named the directives it was describing;
 *   - the next closing directive was the FAQ's, fifteen lines further down;
 *   - #pricing, #faq and the blog band stopped rendering. Not blank -- absent;
 *   - the file still compiled to valid PHP, so BladeViewsCompileTest stayed
 *     green, every page still returned 200, and no error was logged anywhere;
 *   - it was found by rendering the fragment and noticing three ids missing,
 *     then bisecting the comment one line at a time.
 *
 * The same scan found a second instance that had been live for a while: a
 * usage example in user/partials/dropzone-input.blade.php, inside a comment,
 * which meant `UploadPolicy::for('vcf.photo', auth()->user())` was really being
 * executed on every render of that partial.
 *
 * Every directive in this file's patterns is written by concatenation for the
 * same reason -- spelling one out here would arm it in this file too.
 */
class BladeCommentsDoNotHideDirectivesTest extends TestCase
{
    /** The directives pulled out before comments are stripped. */
    private function raw(): array
    {
        $at = '@';

        return [$at . 'php', $at . 'endphp', $at . 'verbatim', $at . 'endverbatim'];
    }

    public function test_no_blade_comment_contains_a_raw_php_directive(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $short = str_replace(resource_path('views/'), '', $file->getPathname());

            // The compiler's own comment pattern: non-greedy, dot-matches-newline.
            if (! preg_match_all('/\{\{--.*?--\}\}/s', $source, $comments, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($comments[0] as [$comment, $offset]) {
                foreach ($this->raw() as $directive) {
                    $at = strpos($comment, $directive);

                    if ($at === false) {
                        continue;
                    }

                    $line = substr_count(substr($source, 0, $offset + $at), "\n") + 1;
                    $offenders[] = "{$short}:{$line}  mentions {$directive} inside a Blade comment";
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "Blade pulls raw PHP blocks out of a template BEFORE it strips comments, so a\n"
            . "comment that names one of these directives opens a real block. Everything from\n"
            . "there to the next closing directive -- which may be hundreds of lines away, in\n"
            . "a section nobody is looking at -- is swallowed. The template still compiles,\n"
            . "the page still returns 200, and the content is simply gone.\n\n"
            . "Describe the directive instead of spelling it: \"a PHP block\", or [php] in\n"
            . "square brackets. It reads the same and cannot fire.\n\n%d found:\n  %s",
            count($offenders),
            implode("\n  ", $offenders)
        ));
    }

    /**
     * The scan is only worth having if it is looking at the whole tree, and a
     * broken path or a renamed directory would make it pass by finding
     * nothing.
     */
    public function test_the_scan_actually_reads_the_view_tree(): void
    {
        $count = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $count++;
            }
        }

        $this->assertGreaterThan(50, $count, 'the view scan found almost nothing; it is not reading the tree');
    }

    /**
     * And the pattern has to actually catch the thing. Built here rather than
     * written literally, so this test file cannot arm itself.
     */
    public function test_the_pattern_catches_a_comment_that_names_the_directive(): void
    {
        $sample = '{{-- describing ' . '@' . 'php here --}}';

        $this->assertMatchesRegularExpression('/\{\{--.*?--\}\}/s', $sample);
        $this->assertStringContainsString('@' . 'php', $sample, 'the sample must contain what the scan looks for');
    }

    /**
     * Real Blade directives, whichever kind of comment they are hiding in.
     *
     * The raw-PHP pair above is the worst case -- it swallows the rest of the
     * file. But any real directive compiles wherever it appears, and Blade has
     * no idea it is inside a comment of any kind. A CSS comment in a <style>
     * block is a comment to the browser and plain text to Blade.
     *
     * That is not hypothetical: extracting the section-surface stylesheet into
     * its own partial carried a CSS comment that used the word "@" + "extends"
     * to describe a rule. Blade compiled it, emitted PHP into the middle of a
     * stylesheet, and every marketing page returned 500. The Blade-comment
     * scan above did not see it, because it was not in a Blade comment.
     *
     * `@media` and `@keyframes` are safe only because Blade has no directive
     * by those names; the moment one existed they would not be. So the list
     * here is Blade's, not a guess.
     */
    private function directives(): array
    {
        $at = '@';

        return array_map(fn ($d) => $at . $d, [
            'extends', 'section', 'endsection', 'yield', 'parent',
            'include', 'includeIf', 'includeWhen', 'includeUnless', 'includeFirst',
            'each', 'push', 'endpush', 'prepend', 'stack',
            'php', 'endphp', 'verbatim', 'endverbatim',
            'if', 'elseif', 'else', 'endif', 'unless', 'endunless',
            'isset', 'endisset', 'empty', 'endempty',
            'foreach', 'endforeach', 'forelse', 'empty', 'endforelse',
            'for', 'endfor', 'while', 'endwhile',
            'auth', 'endauth', 'guest', 'endguest',
            'can', 'endcan', 'cannot', 'endcannot',
            'once', 'endonce', 'props', 'aware', 'error', 'enderror',
            'json', 'class', 'style', 'checked', 'selected', 'disabled', 'readonly', 'required',
            'csrf', 'method', 'dd', 'dump', 'vite', 'inject',
        ]);
    }

    public function test_no_stylesheet_comment_names_a_blade_directive(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $short = str_replace(resource_path('views/'), '', $file->getPathname());

            // Only inside <style> blocks.
            //
            // The first version of this scanned the whole file for /* … */ and
            // reported 37 offenders, every one of them a false positive: `/*`
            // and `*/` occur in JS strings, regexes and url() values, so a
            // non-greedy match across a whole Blade file happily spans from a
            // comment in one place to a terminator hundreds of lines later and
            // swallows real, working directives in between. A <style> block is
            // a region where a /* … */ really is a comment.
            if (! preg_match_all('#<style\b[^>]*>(.*?)</style>#is', $source, $styles, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            foreach ($styles[1] as [$css, $cssOffset]) {
                if (! preg_match_all('#/\*.*?\*/#s', $css, $comments, PREG_OFFSET_CAPTURE)) {
                    continue;
                }

                foreach ($comments[0] as [$comment, $offset]) {
                    foreach ($this->directives() as $directive) {
                        // Word boundary: "@sectional" is not "@section".
                        if (! preg_match('/' . preg_quote($directive, '/') . '(?![\w-])/', $comment, $m, PREG_OFFSET_CAPTURE)) {
                            continue;
                        }

                        $at = $cssOffset + $offset + $m[0][1];
                        $line = substr_count(substr($source, 0, $at), "\n") + 1;
                        $offenders[] = "{$short}:{$line}  names {$directive} in a stylesheet comment";
                    }
                }
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "Blade compiles a directive wherever it appears, and it has no idea it is inside\n"
            . "a comment. A /* … */ in a <style> block is a comment to the browser and plain\n"
            . "text to Blade, which will emit PHP into the middle of your stylesheet.\n\n"
            . "One of these took every marketing page to a 500: a comment describing what a\n"
            . "rule was for happened to name a directive while doing it.\n\n"
            . "Write the name without its leading character, or describe it in words.\n\n%d found:\n  %s",
            count($offenders),
            implode("\n  ", $offenders)
        ));
    }

    /**
     * The scan has to find stylesheet comments at all, or it passes by looking
     * at nothing -- which is how the guard it sits next to failed in the first
     * place.
     */
    public function test_the_stylesheet_scan_finds_comments_to_look_at(): void
    {
        $comments = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            preg_match_all('#<style\b[^>]*>(.*?)</style>#is', (string) file_get_contents($file->getPathname()), $styles);

            foreach ($styles[1] as $css) {
                $comments += preg_match_all('#/\*.*?\*/#s', $css);
            }
        }

        $this->assertGreaterThan(100, $comments, 'the stylesheet-comment scan is finding almost nothing');
    }
}
