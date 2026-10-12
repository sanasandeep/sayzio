<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Blade only treats `@word` as a directive when the character in front of the
 * `@` is not a word character — its pattern is anchored with `\B`. So
 * `remaining seats@endif` is plain text, not a directive.
 *
 * That has two failure modes and only one of them is loud:
 *
 *  - If the matching opener WAS compiled, the block is left hanging, the
 *    compiled view is a PHP parse error, and the page 500s. BladeViewsLintTest
 *    catches that one.
 *  - If the opener is glued too, both sides stay literal, the template parses
 *    cleanly, and the page quietly renders `@if($order->contains_digital) and
 *    any digital downloads have been revoked@endif` to the customer. Nothing
 *    caught that — which is how five of them came to be live at once, on the
 *    store thank-you page, the refund dialog, the inbox upsell and two admin
 *    screens.
 *
 * This guard catches both before either ships. The fix is the `{{ '' }}`
 * spacer already used elsewhere in these views: it compiles to nothing, so the
 * rendered output is unchanged, but it puts a non-word character in front of
 * the `@` so the directive compiles.
 */
class BladeDirectiveSeparatorTest extends TestCase
{
    /**
     * Directives always written with an argument list. Requiring the `(` is
     * what keeps text like `support@class.com` from being read as one.
     */
    private const WITH_ARGS = [
        'if', 'elseif', 'unless', 'isset', 'empty', 'foreach', 'forelse', 'for',
        'while', 'switch', 'case', 'include', 'includeIf', 'includeWhen',
        'includeUnless', 'includeFirst', 'each', 'extends', 'yield', 'section',
        'push', 'pushOnce', 'prepend', 'can', 'cannot', 'canany', 'env',
        'hasSection', 'sectionMissing', 'class', 'style', 'checked', 'selected',
        'disabled', 'readonly', 'required', 'json', 'method', 'props', 'aware',
        'error', 'inject', 'lang', 'choice', 'vite', 'use',
    ];

    /** Directives that stand alone, which is where the glue usually happens. */
    private const BARE = [
        'endif', 'else', 'endunless', 'endisset', 'endempty', 'endforeach',
        'endforelse', 'endfor', 'endwhile', 'endswitch', 'break', 'continue',
        'default', 'endsection', 'stop', 'show', 'append', 'overwrite',
        'endpush', 'endpushOnce', 'endprepend', 'endcan', 'endcannot',
        'endcanany', 'auth', 'endauth', 'guest', 'endguest', 'csrf', 'php',
        'endphp', 'endverbatim', 'once', 'endonce', 'parent', 'enderror',
        'endcomponent', 'endslot', 'production', 'endproduction', 'fragment',
        'endfragment',
    ];

    public function test_no_blade_directive_is_glued_to_a_word_character(): void
    {
        $offences = [];

        foreach ($this->viewFiles() as $file) {
            $raw = file_get_contents($file);
            $source = $this->withoutLiteralRegions($raw);

            foreach ($this->offsets($source) as $offset) {
                $offences[] = sprintf(
                    '%s:%d  %s',
                    ltrim(str_replace(resource_path('views'), '', $file), '/'),
                    substr_count($source, "\n", 0, $offset) + 1,
                    $this->snippet($raw, $offset + 1),
                );
            }
        }

        $this->assertSame([], $offences, sprintf(
            "These `@directive` tokens follow a word character, so Blade does NOT\n".
            "compile them. They render as literal source text, or leave a block\n".
            "unclosed and 500 the page:\n\n  %s\n\n".
            "Put a `{{ '' }}` spacer in front of the `@`. It renders nothing, so the\n".
            "output is byte-identical, and the directive compiles.\n",
            implode("\n  ", $offences),
        ));
    }

    /** @return list<string> */
    private function viewFiles(): array
    {
        $files = [];
        foreach (File::allFiles(resource_path('views')) as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Blank out the regions where an un-compiled `@directive` is the whole
     * point: Blade comments, which is where this trap gets documented, and
     * `@verbatim` blocks, which are handed to the client as written. Newlines
     * are preserved so reported line numbers still match the file.
     */
    private function withoutLiteralRegions(string $source): string
    {
        return (string) preg_replace_callback(
            '/\{\{--.*?--\}\}|@verbatim\b.*?@endverbatim\b/s',
            fn (array $m): string => (string) preg_replace('/[^\n]/', ' ', $m[0]),
            $source,
        );
    }

    /** @return list<int> */
    private function offsets(string $source): array
    {
        $pattern = sprintf(
            '/[A-Za-z0-9_]@(?:(?:%s)[ \t]*\(|(?:%s)\b)/',
            implode('|', array_map('preg_quote', self::WITH_ARGS)),
            implode('|', array_map('preg_quote', self::BARE)),
        );

        if (! preg_match_all($pattern, $source, $found, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        return array_map(static fn (array $m): int => (int) $m[1], $found[0]);
    }

    /** A one-line window around the offending `@`, safe to print. */
    private function snippet(string $source, int $at): string
    {
        $start = max(0, $at - 34);
        $piece = substr($source, $start, 68);
        $piece = (string) mb_convert_encoding($piece, 'UTF-8', 'UTF-8');
        $piece = trim((string) preg_replace('/\s+/', ' ', $piece));

        return ($start > 0 ? '…' : '').$piece.'…';
    }
}
