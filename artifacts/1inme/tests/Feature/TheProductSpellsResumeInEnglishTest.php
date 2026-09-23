<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sana, 2026-09-23, with a screenshot of the resume editor: "non english
 * words or alphabets r used. fix it."
 *
 * He was looking at "Public résumé page", "SHORT LINKS FOR THIS RÉSUMÉ"
 * and "This résumé is surfaced through these short links" -- on the
 * screen a creator uses every time they edit a resume.
 *
 * "Résumé" is a correct English spelling. It is not the one this product
 * uses: the route is /resume, the model is Resume, the button says
 * Resume, and a word carrying two acute accents in the middle of an
 * otherwise plain sentence reads as a typo to most of the audience.
 *
 * There was already a guard for this -- HomepageCopyPunctuationTest --
 * and it passed the whole time, because it only ever looked at
 * resources/views/home/. The marketing site was clean and the product
 * was not. So this one looks at the product: every Blade view a creator
 * or a visitor can land on, and the PHP that feeds them.
 *
 * Comments are exempt, on the same reasoning the homepage guard gives:
 * a comment is not user-visible copy, and a rule that reaches into them
 * only teaches people to write worse comments.
 */
class TheProductSpellsResumeInEnglishTest extends TestCase
{
    /** The spellings that must not appear, including the entity form. */
    private const BANNED = [
        'résumé',
        'Résumé',
        'RÉSUMÉ',
        '&eacute;sum',
        '&Eacute;SUM',
    ];

    /**
     * Every file whose contents can end up on a screen: the app's own
     * views, and the PHP that builds the strings they render.
     *
     * @return array<int, string>
     */
    private function productFiles(): array
    {
        $roots = [
            resource_path('views'),
            app_path(),
            base_path('routes'),
        ];

        $files = [];
        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
            foreach ($it as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $name = $file->getFilename();
                if (str_ends_with($name, '.blade.php') || str_ends_with($name, '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }

    /**
     * Blank out the comment forms so only copy is left. Replaced with
     * spaces rather than removed, so the reported line number is real.
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

    /**
     * The one test. Reports every offender at once, with its line, so a
     * failure is a list to fix rather than a scavenger hunt.
     */
    public function test_nothing_a_creator_can_see_says_resume_with_accents(): void
    {
        $found = [];

        foreach ($this->productFiles() as $path) {
            $copy = $this->stripComments((string) file_get_contents($path));

            foreach (explode("\n", $copy) as $i => $line) {
                foreach (self::BANNED as $needle) {
                    if (str_contains($line, $needle)) {
                        $short = str_replace(base_path().'/', '', $path);
                        $found[] = $short.':'.($i + 1).'  '.trim(substr($line, 0, 120));
                        break;
                    }
                }
            }
        }

        $this->assertSame([], $found, sprintf(
            "This product spells it \"resume\" -- the route, the model and the buttons all do.\n"
            ."%d place(s) still carry the accents:\n  %s",
            count($found),
            implode("\n  ", $found)
        ));
    }

    /**
     * The resume editor is the screen Sana was looking at, so assert on
     * the rendered page rather than only on the source. A string built in
     * PHP and echoed into the view would pass the scan above and still
     * show up here.
     */
    public function test_the_rendered_resume_editor_spells_it_in_english(): void
    {
        $user = \App\Modules\User\Models\User::factory()->create(['onboarded_at' => now()]);

        $html = $this->actingAs($user)->get('/user/resume')->assertOk()->getContent();

        // Entities render as the character, so normalise before looking.
        $visible = str_replace(['&eacute;', '&Eacute;'], ['é', 'É'], (string) $html);
        $visible = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', (string) $visible);

        foreach (['résumé', 'Résumé', 'RÉSUMÉ'] as $needle) {
            $this->assertStringNotContainsString($needle, (string) $visible,
                'the resume editor is the screen this was reported from');
        }
    }
}
