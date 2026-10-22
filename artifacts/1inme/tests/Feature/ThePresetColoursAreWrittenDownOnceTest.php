<?php

namespace Tests\Feature;

use App\Modules\Common\Support\NavigationAppearance;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A preset's colour is stated in one place and read everywhere else.
 *
 * THE DRIFT THIS ENDS
 *
 * The same facts were written down three times: in PALETTES, as literals
 * in the preset rules, and as hand-written swatch CSS on the Design
 * page. Nothing held them together, and two of the three had already
 * gone stale without anything reporting it:
 *
 *   The Design page painted the Dark swatch #111827 -- the navy from
 *   before the preset was neutralised, two colour changes behind. A
 *   swatch showing the wrong colour still looks like a swatch.
 *
 *   PALETTES itself still said #18181b after the preset rule had moved
 *   to #0b0a11, so the array that is supposed to describe the preset was
 *   a generation behind the CSS.
 *
 * Neither broke anything loudly. That is the point: this file exists so
 * the next retune cannot leave a copy behind, because there is no copy.
 *
 * The assertions compare the RENDERED page against the palette, not the
 * blade source against the palette. A template can be correct and still
 * not reach the page, which is the .navrail-rail failure in its purest
 * form.
 */
class ThePresetColoursAreWrittenDownOnceTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    private function dashboard(): string
    {
        return (string) $this->actingAs($this->owner())->get('/user/dashboard')->assertOk()->getContent();
    }

    private function designPage(): string
    {
        return (string) $this->actingAs($this->owner())->get(route('user.design.edit'))->assertOk()->getContent();
    }

    /**
     * Every token in a solid preset's rule is the palette's value for it.
     */
    public function test_each_solid_preset_rule_is_emitted_from_the_palette(): void
    {
        $html = $this->dashboard();
        $checked = 0;

        foreach (array_keys(NavigationAppearance::PRESETS) as $preset) {
            $tokens = NavigationAppearance::tokens($preset);

            if ($tokens === []) {
                continue;
            }

            $ok = preg_match(
                '/html\[data-rail-appearance="' . preg_quote($preset, '/') . '"\]\s+\.navrail\s*\{([^}]*)\}/',
                $html,
                $m
            );
            $this->assertSame(1, $ok, "The '{$preset}' rule for the rail is not on the page.");

            foreach (NavigationAppearance::TOKENS as $token) {
                $this->assertStringContainsString(
                    '--nav-' . $token . ':' . $tokens[$token],
                    $m[1],
                    "The '{$preset}' rule's --nav-{$token} is not the palette's value."
                );
                $checked++;
            }
        }

        $this->assertGreaterThan(10, $checked, 'No solid preset was checked, so this test proves nothing.');
    }

    /**
     * The picker's swatch shows what the preset paints.
     */
    public function test_every_swatch_is_generated_from_the_palette(): void
    {
        $html = $this->designPage();

        foreach (array_keys(NavigationAppearance::PRESETS) as $preset) {
            $this->assertStringContainsString(
                '.design-swatch-' . $preset . ' {background:' . NavigationAppearance::swatch($preset) . '}',
                $html,
                "The '{$preset}' swatch is not generated from the palette."
            );
        }

        $this->assertStringContainsString(
            '.design-swatch-default {background:' . NavigationAppearance::swatch('') . '}',
            $html,
            'The Admin default swatch is not generated from the palette.'
        );
    }

    /**
     * The specific colour that was wrong, named so a reintroduction is
     * caught by name rather than by someone noticing.
     */
    public function test_the_stale_navy_is_gone_from_the_picker(): void
    {
        $html = $this->designPage();

        $this->assertStringNotContainsString(
            '#111827',
            $html,
            'The Design page still carries the navy the Dark preset stopped using two changes ago.'
        );

        // The control. An empty page, or a page that failed to render the
        // swatches at all, would satisfy the assertion above.
        $this->assertStringContainsString(
            NavigationAppearance::PALETTES['dark']['surface'],
            $html,
            'The Design page does not show the Dark preset at all, so the check above proves nothing.'
        );
    }

    /**
     * The palette and the rule cannot disagree, because only one of them
     * is written by hand. Stated as a property rather than as the two
     * values, so a retune does not have to edit this file.
     */
    public function test_the_dark_preset_and_its_palette_entry_cannot_disagree(): void
    {
        $html = $this->dashboard();
        $surface = NavigationAppearance::PALETTES['dark']['surface'];

        $ok = preg_match('/html\[data-rail-appearance="dark"\]\s+\.navrail\s*\{([^}]*)\}/', $html, $m);
        $this->assertSame(1, $ok);

        preg_match('/--nav-surface:\s*([^;]+)/', $m[1], $found);
        $this->assertSame(
            $surface,
            trim($found[1] ?? ''),
            'The rule and the palette disagree about what Dark is.'
        );
    }
}
