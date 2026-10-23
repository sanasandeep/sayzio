<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Selection is not an action, and the list of places that forget it is
 * enumerated rather than noticed.
 *
 * The button system neutralised `.editor-tab` and `.pill-tab` -- the two
 * active tabs visible on the page being looked at while it was written. It
 * shipped, and the inner tab row on a link's Settings was still a blue
 * gradient, because `.settings-tab` is a third class nobody had opened that
 * day. Fixing that one by hand would have left the next one to be found the
 * same way: by a person noticing it after a deploy.
 *
 * So this test does the finding. It scans every blade file for a rule whose
 * selector carries an active / aria-selected state and whose body paints a
 * gradient or the accent, and requires each one to be either neutralised in
 * the button system or named in ALLOWED_COLOURED below, with a reason.
 *
 * Add a new tab with a blue active state and this fails, naming it.
 */
class EverySelectionControlIsNeutralTest extends TestCase
{
    /**
     * Selection states that keep their colour, on purpose.
     *
     * `.sidebar-link.active` is nav chrome: it belongs to the navigation
     * appearance presets, which tint it per rail/panel colourway, and
     * flattening it here would fight that system.
     *
     * `.qr-shape-card.active` and the carousel dots are not tabs. The card is
     * a preview tile in a grid of look-alike shapes, where the accent is the
     * only thing saying which one is chosen; a 10% ink tint would be a worse
     * signal, not a quieter one.
     */
    private const ALLOWED_COLOURED = [
        'sidebar-link',
        'qr-shape-card',
        'ehb-dot',
        'hero-dot',
        'step-dot',
    ];

    public function test_every_coloured_selection_state_is_neutralised_or_allowed(): void
    {
        $system = file_get_contents(resource_path('views/common/partials/button-system.blade.php'));
        $this->assertNotEmpty($system, 'the button system partial is missing');

        $unhandled = [];

        foreach ($this->colouredSelectionRules() as $selector => $files) {
            if ($this->isAllowed($selector)) {
                continue;
            }

            // Neutralised when the button system names the same control. The
            // class is the handle; the exact selector shape differs (the
            // system prefixes `html ` to win on specificity).
            if ($this->systemNames($system, $selector)) {
                continue;
            }

            $unhandled[] = sprintf('%s   (%s)', $selector, implode(', ', $files));
        }

        $this->assertSame([], $unhandled, sprintf(
            "These controls paint their selected state with the accent or a gradient, and\n"
            . "the button system does not neutralise them -- so they stay blue while every\n"
            . "tab beside them is neutral:\n\n%s\n\n"
            . "Either add the selector to the neutral-tint rule in\n"
            . "common/partials/button-system.blade.php, or add its class to\n"
            . "ALLOWED_COLOURED in this test with a reason.",
            implode("\n", $unhandled)
        ));
    }

    /**
     * The control: the scanner finds things.
     *
     * A regex that silently matched nothing would make the test above pass
     * forever, which looks exactly like the app being correct. These are
     * states that exist today and are known to be coloured at the source.
     */
    public function test_the_scanner_finds_the_states_that_are_known_to_exist(): void
    {
        $found = array_keys($this->colouredSelectionRules());

        $this->assertNotEmpty($found, 'the scanner found no coloured selection states at all');

        foreach (['editor-tab', 'settings-tab', 'palette-tab'] as $known) {
            $hit = array_filter($found, fn ($s) => str_contains($s, $known));
            $this->assertNotEmpty($hit, "the scanner did not find .{$known}, which is coloured at the source");
        }
    }

    /** And the control for the allowlist: it is not swallowing everything. */
    public function test_the_allowlist_does_not_swallow_the_tabs(): void
    {
        foreach (['editor-tab', 'settings-tab', 'pill-tab', 'palette-tab'] as $tab) {
            $this->assertFalse(
                $this->isAllowed(".{$tab}.is-active"),
                ".{$tab} is being excused by ALLOWED_COLOURED, so the main test cannot see it"
            );
        }
    }

    private function isAllowed(string $selector): bool
    {
        foreach (self::ALLOWED_COLOURED as $class) {
            if (str_contains($selector, $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the button system neutralise this control?
     *
     * Matched on the class rather than the whole selector: the system writes
     * `html .settings-tab.is-active` where the source writes
     * `html.light-mode .settings-tab.is-active`, and both are the same
     * control. The class has to appear inside the neutral-tint rule, not just
     * anywhere in the file.
     */
    private function systemNames(string $system, string $selector): bool
    {
        if (! preg_match_all('/\.([a-z][a-z0-9-]*)/i', $selector, $m)) {
            return false;
        }

        // The neutral-tint rule, from its first selector to its closing brace.
        if (! preg_match('/html \.editor-tab\.is-active,(.*?)\}/s', $system, $block)) {
            return false;
        }
        $rule = $block[0];

        foreach ($m[1] as $class) {
            if (in_array($class, ['is-active', 'active', 'light-mode', 'aurora'], true)) {
                continue;
            }
            if (str_contains($rule, '.' . $class)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every rule in the views whose selector carries a selected state and
     * whose body paints a gradient or the accent.
     *
     * @return array<string,array<int,string>> selector => files
     */
    private function colouredSelectionRules(): array
    {
        $found = [];

        $root = resource_path('views');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $text = file_get_contents($file->getPathname());
            if ($text === false) {
                continue;
            }

            if (! preg_match_all('/([^{}\n;][^{}]*?)\{([^{}]*)\}/s', $text, $rules, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($rules as [$whole, $selector, $body]) {
                $selector = trim(preg_replace('/\s+/', ' ', $selector));

                // Long "selectors" are almost always a false positive from
                // matching something that is not CSS at all.
                if ($selector === '' || strlen($selector) > 130) {
                    continue;
                }
                if (! preg_match('/\.is-active|\.active\b|aria-selected/', $selector)) {
                    continue;
                }
                if (! str_contains($body, 'background')) {
                    continue;
                }
                if (! preg_match('/gradient|var\(--accent/', $body)) {
                    continue;
                }

                $found[$selector][] = $file->getFilename();
                $found[$selector] = array_values(array_unique($found[$selector]));
            }
        }

        ksort($found);

        return $found;
    }
}
