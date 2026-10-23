<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two faults where the CSS was right and the CASCADE was not.
 *
 * Both are the same shape as the bugs this file's neighbours guard, and
 * both came back after a fix that looked complete:
 *
 *   #565 removed the aurora rule that deleted the rail card's shadow.
 *   What painted afterwards was dash-glass's own drop -- 8% black, which
 *   against a white page is all but invisible. The first fix was real
 *   and was only half of it.
 *
 *   The account block is a SIBLING of .navrail, so it takes its tokens
 *   from the panel's scope. Minimised, it occupies the rail's column
 *   exactly -- measured x=15 w=55 against a rail at x=15 w=55 -- and
 *   painted #ffffff inside a #0b0a11 rail.
 */
class TheChromeKeepsItsEdgesTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard(): string
    {
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();

        return (string) $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();
    }

    /**
     * The card swaps dash-glass's drop for the theme's card shadow.
     *
     * Placement and specificity are both load-bearing, so both are
     * asserted. A bare `.dash-glass.rail-card` is (0,2,0) and
     * `html.light-mode .dash-glass` is (0,2,1): on its own the override
     * loses in light mode, which is the one theme where the card is hard
     * to see. It has to come after that rule and match its specificity.
     */
    public function test_the_rail_card_takes_the_card_shadow_in_both_themes(): void
    {
        $html = $this->dashboard();

        $this->assertStringContainsString(
            '.dash-glass.rail-card { --glass-drop: var(--card-shadow-hover); }',
            $html,
            'The rail card no longer swaps the glass drop for the card shadow.'
        );

        // Specificity: the light-qualified selector must be in the list.
        $this->assertStringContainsString(
            'html.light-mode .dash-glass.rail-card,',
            $html,
            'The override is not qualified for light mode, where it loses to html.light-mode .dash-glass.'
        );

        // Order: it must come after the rule it overrides.
        $lightRule = strpos($html, 'html.light-mode .dash-glass {');
        $override  = strpos($html, 'html.light-mode .dash-glass.rail-card,');
        $this->assertNotFalse($lightRule);
        $this->assertNotFalse($override);
        $this->assertGreaterThan(
            $lightRule,
            $override,
            'The override comes before the rule it overrides, so it loses on source order.'
        );

        // And the drop has to be a variable both rules can set, or the
        // swap has nothing to swap.
        $this->assertStringContainsString(
            'box-shadow: var(--glass-inset), var(--glass-drop) !important;',
            $html,
            'dash-glass no longer reads its drop from a variable, so the card cannot replace it.'
        );

        // The control: dash-glass must still state its own soft drop for
        // every other surface, or the card is not overriding anything.
        $this->assertStringContainsString(
            '--glass-drop: 0 12px 32px rgba(0, 0, 0, 0.08);',
            $html,
            'The chrome drop is gone entirely, so the override above proves nothing.'
        );
    }

    /** Minimised, the account block is rail chrome and takes rail colours. */
    public function test_the_minimised_account_block_takes_the_rails_colours(): void
    {
        $html = $this->dashboard();

        $this->assertStringContainsString(
            '.navrail-wrap[data-rail-min="1"] .navrail-account',
            $html,
            'The minimised account block is not in the rail scope, so it keeps the panel colour inside the rail.'
        );

        // It must be scoped to the MINIMISED state only: expanded, the
        // block spans the panel and should follow the panel.
        $this->assertStringNotContainsString(
            ':is(.navrail, .navrail-account)',
            $html,
            'The account block takes rail colours even when expanded, where it belongs to the panel.'
        );

        // The control: the rail scope still covers the rail itself.
        $this->assertMatchesRegularExpression(
            '/data-rail-appearance="dark"\]\s+:is\(\.navrail,/',
            $html,
            'The rail scope no longer names .navrail, so the whole preset is broken.'
        );
    }
}
