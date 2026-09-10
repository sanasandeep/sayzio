<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Plain-English punctuation in the marketing copy.
 *
 * A guard already bans the em dash (U+2014) under resources/views/public/,
 * which is why the trust band carries a comment explaining why its sub-line
 * uses a colon. That guard never looked at resources/views/home/ -- and the
 * homepage is where almost all of the marketing copy actually lives. A
 * hundred and five of them had accumulated there, most in the hero and the
 * feature sections, which is exactly where they are most visible.
 *
 * The same pass dropped the accents from "résumé". It is a correct English
 * spelling, but it is not the one the product uses anywhere else, and a
 * word with two acute accents in the middle of an otherwise plain sentence
 * reads as a typo to most of the audience.
 *
 * This test extends the existing rule to the homepage views so the next
 * section written does not quietly reintroduce either.
 *
 * Comments are exempt. A `{{-- --}}`, `<!-- -->`, `/* *\/` or `//` comment
 * is not user-visible copy, and a rule that reaches into them only teaches
 * people to write worse comments.
 */
class HomepageCopyPunctuationTest extends TestCase
{
    /**
     * The rendered-page test reads copy out of zio_lines and testimonials,
     * both of which are seeded by their create migrations. Building the
     * database from migrations is what makes that assertion mean "a fresh
     * install is clean" rather than "whatever is in the shared test
     * database happens to be clean today".
     */
    use RefreshDatabase;

    private const EM_DASH = "\u{2014}";

    /** Every Blade file that makes up the homepage. */
    private function homeViews(): array
    {
        $files = [resource_path('views/home.blade.php')];

        $dir = resource_path('views/home');
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
     * Strip the comment forms Blade files use, so only copy is left.
     *
     * Replaced with spaces rather than removed, to keep byte offsets --
     * the failure message reports a line number and it has to be the real
     * one.
     */
    private function stripComments(string $source): string
    {
        $patterns = [
            '/\{\{--.*?--\}\}/s',   // Blade
            '/<!--.*?-->/s',        // HTML
            '/\/\*.*?\*\//s',       // CSS and PHP block
            '/\/\/[^\n]*/',         // JS and PHP line
        ];

        foreach ($patterns as $p) {
            $source = preg_replace_callback(
                $p,
                fn ($m) => preg_replace('/[^\n]/', ' ', $m[0]),
                $source
            );
        }

        return $source;
    }

    /** Report offenders as "file:line  <the offending line>". */
    private function offenders(string $needle): array
    {
        $found = [];

        foreach ($this->homeViews() as $path) {
            $copy = $this->stripComments((string) file_get_contents($path));
            foreach (explode("\n", $copy) as $i => $line) {
                if (str_contains($line, $needle)) {
                    $short = str_replace(resource_path('views/'), '', $path);
                    $found[] = $short . ':' . ($i + 1) . '  ' . trim(substr($line, 0, 120));
                }
            }
        }

        return $found;
    }

    public function test_the_homepage_copy_has_no_em_dashes(): void
    {
        $found = $this->offenders(self::EM_DASH);

        $this->assertSame([], $found, sprintf(
            "The homepage copy must not use em dashes (U+2014); a comma, colon or full stop\n"
            . "reads as plain English and matches the guard already applied to\n"
            . "resources/views/public/. %d found:\n  %s",
            count($found),
            implode("\n  ", $found)
        ));
    }

    /** The HTML entity is the same character wearing a hat. */
    public function test_the_homepage_copy_has_no_em_dash_entities(): void
    {
        $found = array_merge($this->offenders('&mdash;'), $this->offenders('&#8212;'));

        $this->assertSame([], $found, "An em dash entity is still an em dash:\n  " . implode("\n  ", $found));
    }

    /**
     * The views are only half the copy. Zio's hero lines and the
     * testimonials moved into admin-editable tables, so a scan of Blade
     * files cannot see them -- and those were exactly the four sentences
     * still showing an em dash after every view had been cleaned.
     *
     * This asserts on the rendered page instead, which covers both sources
     * at once. <script> and <style> contents are excluded: a dash in a CSS
     * comment or a JS string is not copy, and the box-drawing characters
     * this codebase uses to head its comment blocks would fail it forever.
     */
    public function test_the_rendered_homepage_shows_no_em_dash(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Drop the two element types whose text is code, not copy.
        $visible = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $html);
        // Entities render as the character, so normalise before looking.
        $visible = str_replace(['&mdash;', '&#8212;'], self::EM_DASH, (string) $visible);

        if (! str_contains($visible, self::EM_DASH)) {
            $this->assertTrue(true);

            return;
        }

        // Quote what was found, so the failure names the sentence to fix
        // rather than leaving someone to search the page for a dash.
        preg_match_all('/[^<>]{0,60}' . self::EM_DASH . '[^<>]{0,60}/u', $visible, $m);
        $this->fail(
            "The rendered homepage still shows an em dash. If it is not in a Blade file it is\n"
            . "in the database (zio_lines, testimonials), edited through admin:\n  "
            . implode("\n  ", array_map('trim', array_slice($m[0], 0, 12)))
        );
    }

    public function test_the_homepage_spells_resume_without_accents(): void
    {
        $found = array_merge(
            $this->offenders('résumé'),
            $this->offenders('Résumé'),
            $this->offenders('&eacute;sum'),
        );

        $this->assertSame([], $found, "Use \"resume\", not \"résumé\":\n  " . implode("\n  ", $found));
    }
}
