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
}
