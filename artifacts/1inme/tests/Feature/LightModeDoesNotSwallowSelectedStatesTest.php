<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * A light-mode override must not erase the "this one is selected" styling.
 *
 * The pattern that keeps biting: a component defines its resting look and then
 * its selected look as a second class --
 *
 *     .sa-gate-tab            { background: <resting> }
 *     .sa-gate-tab.sa-gate-on { background: <accent>; color: #fff }
 *
 * -- and the light theme repaints the resting look with a prefixed selector:
 *
 *     html.light-mode .sa-gate-tab { background: #f1f5f9; color: #475569 }
 *
 * That prefixed rule is (0,2,1). The selected rule is (0,2,0). So in light mode
 * the RESTING fill wins on the selected element, while `color:#fff` from the
 * light block's own state rule stays -- and the Email tab rendered white text
 * on #f1f5f9. Invisible.
 *
 * The same swallow hid the chat mic's recording state: a live mic looked
 * identical to an idle one, which is the single state a person must never have
 * to guess at.
 *
 * The fix is always the same: the light-mode state rule restates the fill (and
 * the border, when the light-mode resting rule used the `border` shorthand),
 * not just the text colour.
 *
 * KNOWN_UNFIXED below records the cases found by this scan that have not been
 * corrected yet -- each needs a light-ground colour chosen and looked at, not a
 * copy of the dark accent. Delete entries as they are fixed; never add one to
 * silence a new break.
 */
class LightModeDoesNotSwallowSelectedStatesTest extends TestCase
{
    /** Real breaks, awaiting a designed light-mode colour. @var array<string, string[]> */
    private const KNOWN_UNFIXED = [
        'user/links/conversational/editor.blade.php'      => ['cv-sim-chip.is-picked'],
        'common/events-directory.blade.php'               => ['hero-dot.active'],
        'common/partials/events-hero-band.blade.php'      => ['ehb-dot.active'],
        'common/partials/ai-dashboard-demo.blade.php'     => ['aidd-tab.is-active'],
        'home/partials/audience-visual-style.blade.php'   => ['av-swap-row.is-now'],
        'home/partials/expandable-cards.blade.php'        => ['lts-btn.lts-solid', 'lts-card.lts-accent'],
    ];

    public function test_no_selected_state_loses_its_fill_in_light_mode(): void
    {
        $offenders = [];

        foreach ($this->viewsWithLightModeOverrides() as $relative => $source) {
            foreach ($this->swallowedStates($source) as $pair) {
                if (in_array($pair, self::KNOWN_UNFIXED[$relative] ?? [], true)) {
                    continue;
                }
                $offenders[] = "{$relative}  .{$pair}";
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['A selected state is painted over by its own light-mode resting rule.',
             'Restate background (and border, if the light rule used the shorthand)',
             'in the html.light-mode .a.b rule:', ''],
            $offenders
        )));
    }

    /** The already-fixed cases stay fixed. */
    public function test_the_chat_gate_and_mic_keep_their_light_mode_fills(): void
    {
        $css = file_get_contents(
            base_path('resources/views/common/partials/site-assistant.blade.php')
        );

        foreach ([
            'html.light-mode .sa-gate-tab.sa-gate-on' => 'the selected Email/Phone tab',
            'html.light-mode .sa-mic.sa-mic-rec'      => 'the mic while recording',
            'html.light-mode .sa-snap.sa-snap-on'     => 'the snapshot-attached state',
        ] as $selector => $what) {
            $this->assertMatchesRegularExpression(
                '/' . preg_quote($selector, '/') . '\s*\{[^}]*background/',
                $css,
                "{$what} must set its own background in light mode"
            );
        }
    }

    /** @return array<string, string> relative path => source */
    private function viewsWithLightModeOverrides(): array
    {
        $root = base_path('resources/views');
        $out = [];

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($rii as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            if (str_contains($source, 'html.light-mode')) {
                $out[str_replace($root . '/', '', $file->getPathname())] = $source;
            }
        }

        return $out;
    }

    /**
     * Selected-state rules whose fill the light-mode resting rule outranks.
     *
     * @return string[]  e.g. ["sa-gate-tab.sa-gate-on"]
     */
    private function swallowedStates(string $source): array
    {
        // .a.b { ... background ... }  -- a state that paints itself
        preg_match_all('/^\s*\.([a-z0-9-]+)\.([a-z0-9-]+)\s*\{([^}]*)\}/mi', $source, $base, PREG_SET_ORDER);
        // html.light-mode .a { ... background ... }  -- repaints the resting look
        preg_match_all('/^\s*html\.light-mode \.([a-z0-9-]+)\s*\{([^}]*)\}/mi', $source, $single, PREG_SET_ORDER);
        // html.light-mode .a.b { ... }  -- the state's own light-mode rule
        preg_match_all('/^\s*html\.light-mode \.([a-z0-9-]+)\.([a-z0-9-]+)\s*\{([^}]*)\}/mi', $source, $state, PREG_SET_ORDER);

        $repainted = [];
        foreach ($single as $m) {
            if (str_contains($m[2], 'background')) $repainted[$m[1]] = true;
        }

        $restated = [];
        foreach ($state as $m) {
            if (str_contains($m[3], 'background')) $restated[$m[1] . '.' . $m[2]] = true;
        }

        $out = [];
        foreach ($base as $m) {
            if (! str_contains($m[3], 'background')) continue;
            $pair = $m[1] . '.' . $m[2];
            if (isset($repainted[$m[1]]) && ! isset($restated[$pair])) {
                $out[$pair] = true;
            }
        }

        return array_keys($out);
    }
}
