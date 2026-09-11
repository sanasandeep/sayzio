<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Every field the site-page editors show an admin is a field the controller
 * will keep.
 *
 * This is the general form of the EEFind bug. `$request->validate($rules)`
 * returns only the keys it has rules for, so a field can have a complete
 * editor UI, submit correctly, save with a success message, and be silently
 * discarded -- because nobody added its rule. The parent-company block on
 * /about lived that way: nine fields and a stats repeater, all inert, and
 * because the sanitiser substitutes defaults for an absent block it came back
 * looking plausible rather than empty.
 *
 * Nothing about that failure is specific to EEFind. The next section added to
 * one of these editors can repeat it exactly, and would look just as fine.
 * So rather than only fixing the one block, this walks the editors and checks
 * the pairing.
 *
 * It reads the controller's source for its `$rules[...]` assignments, which
 * is not lovely -- but the rules are assembled inside update(), per slug,
 * against a request, so there is no way to ask for them. The alternative is
 * a round-trip test per field, which is roughly a hundred requests to say
 * what one string comparison says.
 */
class EveryEditorFieldIsActuallySavedTest extends TestCase
{
    private const CONTROLLER = 'app/Modules/Admin/Controllers/SitePageController.php';

    private const EDITORS = 'resources/views/admin/site-pages/partials';

    /** Every `extra.…` path the controller has a validation rule for. */
    private function validatedPaths(): array
    {
        $source = file_get_contents(base_path(self::CONTROLLER));
        $this->assertNotFalse($source, 'the site-page controller has moved');

        $paths = [];

        // $rules['extra.foo.bar'] = …
        preg_match_all('/\$rules\[\s*[\'"](extra[^\'"]*)[\'"]\s*\]/', $source, $bracket);
        $paths = array_merge($paths, $bracket[1]);

        // 'extra.foo' => 'nullable|…'  (the base $rules array literal)
        preg_match_all('/[\'"](extra\.[^\'"]+)[\'"]\s*=>/', $source, $literal);
        $paths = array_merge($paths, $literal[1]);

        // $rules['extra.section_visibility.' . $slug] — a rule built per slug.
        // Treated as covering the whole subtree, since the slugs come from the
        // same list the editor loops over.
        preg_match_all('/\$rules\[\s*[\'"](extra\.[^\'"]*)\.[\'"]\s*\.\s*\$/', $source, $concat);
        foreach ($concat[1] as $prefix) {
            $paths[] = $prefix . '.*';
        }

        return array_values(array_unique($paths));
    }

    private function isCovered(string $path, array $rules): bool
    {
        foreach ($rules as $rule) {
            $pattern = '/^' . str_replace('\*', '[^.]+', preg_quote($rule, '/')) . '$/';
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }

    public function test_no_editor_field_is_silently_discarded_on_save(): void
    {
        $rules = $this->validatedPaths();
        $this->assertNotEmpty($rules, 'no validation rules were found at all -- the regex has gone stale');

        $editors = glob(base_path(self::EDITORS) . '/*.blade.php');
        $this->assertNotEmpty($editors, 'the site-page editor partials have moved');

        $orphans = [];

        foreach ($editors as $editor) {
            $markup = file_get_contents($editor);

            preg_match_all('/extra\[[a-z_]+\](?:\[[^\]]*\])*/i', $markup, $found);

            foreach (array_unique($found[0]) as $raw) {
                // Repeater indexes, literal or Alpine-bound, all mean "any row".
                $path = preg_replace('/\[[\'"]?\s*\+?\s*i\s*\+?\s*[\'"]?\]|\[\d+\]|\[\]/', '[*]', $raw);
                $path = str_replace(['[', ']'], ['.', ''], $path);
                $path = preg_replace('/\.\.+/', '.', $path);

                // A Blade- or Alpine-interpolated segment cannot be resolved
                // from the markup. Those are checked by hand; skipping them
                // here keeps the test honest rather than green-by-accident.
                if (str_contains($path, '{{') || str_contains($path, '+')) {
                    continue;
                }

                if (! $this->isCovered($path, $rules)) {
                    $orphans[] = basename($editor) . ' → ' . $path;
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($orphans)),
            "These editor fields have no validation rule, so validate() strips them and the "
            . "admin's edit is discarded with a success message:\n  "
            . implode("\n  ", array_unique($orphans))
            . "\n\nAdd a rule for each in SitePageController::update(), under the branch for "
            . 'that page slug.'
        );
    }
}
