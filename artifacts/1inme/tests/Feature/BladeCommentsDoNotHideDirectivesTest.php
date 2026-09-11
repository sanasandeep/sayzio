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
     * The third place this hides: a PHP comment inside a raw PHP block.
     *
     * Blade finds those blocks by matching an opening directive to the FIRST
     * closing one after it. It does that on the raw source, so it cannot tell
     * a closing directive in code from one written inside a `//` or `/* *​/`
     * comment in the same block -- and the one in the comment comes first.
     * The block ends there, the real closing directive leaks out as literal
     * text, and every statement after the comment stops being code.
     *
     * Which is a slower failure than it sounds. The file still compiles. The
     * page still returns 200. The variables those statements were assigning
     * are simply never assigned, so the error surfaces wherever they are
     * eventually read -- possibly a different file, three includes away.
     *
     * This is exactly what happened while writing the comment that explains
     * why the site-assistant partial computes its mascot's size at the top of
     * the file. The comment said the words. The two assignments below it fell
     * outside the block. All 42 marketing pages returned 500, complaining
     * about an undefined variable 340 lines further down.
     *
     * Only the two closing directives are scanned. An opening one inside a
     * block is harmless -- the block is already open -- and describing "a
     * [php] block" in a comment is a thing people reasonably do.
     *
     * The scan walks the directives rather than matching the block with one
     * pattern, and that is not a stylistic choice. The first version of this
     * test did use one pattern -- open, capture the body non-greedily, close.
     * It reported nothing when the bug was deliberately reintroduced, because
     * the body it captures STOPS at the offending closer: the very text being
     * looked for is the thing that ends the capture, so it is never inside it.
     * A guard that cannot see the defect it was written for is worse than no
     * guard, since it also says everything is fine.
     */
    public function test_no_php_block_comment_closes_the_block_it_is_inside(): void
    {
        $at = '@';
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

            // Raw PHP blocks only, not verbatim ones. A verbatim block holds
            // arbitrary text -- JavaScript, usually -- and asking PHP's
            // tokenizer whether a point in it is inside a comment is a
            // question about the wrong language. The same trap is there in
            // principle; it has never fired, and a guess would be worse than
            // the silence.
            preg_match_all(
                '/(?<![\w@])' . $at . '(php|endphp)\b/',
                $source,
                $marks,
                PREG_OFFSET_CAPTURE | PREG_SET_ORDER
            );

            $bodyStart = null;

            foreach ($marks as $mark) {
                [$text, $offset] = $mark[0];
                $opening = $mark[1][0] === 'php';

                if ($opening) {
                    // Blade does not nest these: an opener inside an open
                    // block is just text.
                    $bodyStart ??= $offset + strlen($text);

                    continue;
                }

                if ($bodyStart === null) {
                    continue;
                }

                // This closer is the one that ends the block. Is it sitting
                // inside a comment in the code above it?
                if ($this->fallsInsideAComment(substr($source, $bodyStart, $offset - $bodyStart))) {
                    $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                    $offenders[] = "{$short}:{$line}  names {$text} in a comment inside a raw PHP block";
                }

                $bodyStart = null;
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "Blade closes a raw PHP block at the FIRST closing directive after the opening\n"
            . "one, and it reads the raw source -- so one written inside a PHP comment in\n"
            . "that block ends it right there. Every statement after the comment stops being\n"
            . "code, silently: the file compiles, the page returns 200, and the failure\n"
            . "surfaces as an undefined variable somewhere else entirely.\n\n"
            . "Describe the directive instead of spelling it -- \"the closing tag of the\n"
            . "block\", or [endphp] in square brackets.\n\n%d found:\n  %s",
            count($offenders),
            implode("\n  ", $offenders)
        ));
    }

    /**
     * Does the point just past this PHP source sit inside an open comment?
     *
     * `$body` is everything between the block's opening directive and the
     * closer being judged, so "is that closer commented out" becomes "is this
     * text still inside a comment when it runs out".
     *
     * Answered with PHP's own tokenizer, which is the only thing that reliably
     * knows the difference between a comment and a comment's punctuation
     * inside a string. Matching `/*` and `//` by pattern does not: the first
     * version of this did, and reported four offenders that were nothing of
     * the kind. `'image/*'` and `'* / *'` -- the MIME wildcards in the admin
     * domain form and the file-field default -- contain the two characters
     * that open a block comment, with nothing after them that closes it, so a
     * pattern reads the rest of the file as commented out. The tokenizer sees
     * a string.
     */
    private function fallsInsideAComment(string $body): bool
    {
        $tokens = @token_get_all('<?php ' . $body);
        $last = end($tokens);

        if (! is_array($last) || ! in_array($last[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            return false;
        }

        // A comment that ended properly took its terminator with it: a `//`
        // one ends at its newline, which the token text includes, and a block
        // one ends at its own closer. Anything else is still open where the
        // body stops -- which is exactly where the directive was written.
        $text = $last[1];

        return ! str_ends_with($text, "\n") && ! str_ends_with(rtrim($text), '*/');
    }

    /** And that scan has to be looking at real blocks with real comments in them. */
    public function test_the_php_block_scan_finds_blocks_with_comments_in_them(): void
    {
        $at = '@';
        $withComments = 0;

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            preg_match_all(
                '/(?<![\w@])' . $at . 'php\b(.*?)(?<![\w@])' . $at . 'endphp\b/s',
                (string) file_get_contents($file->getPathname()),
                $blocks
            );

            foreach ($blocks[1] as $body) {
                $withComments += preg_match('#//[^\n]*|/\*.*?\*/#s', $body);
            }
        }

        $this->assertGreaterThan(50, $withComments, 'the raw-PHP-block scan is finding almost no commented blocks');
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
