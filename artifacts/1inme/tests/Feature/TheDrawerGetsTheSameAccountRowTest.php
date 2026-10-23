<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The mobile drawer carries the same account row as the rail.
 *
 * #553 replaced three stacked blocks at the foot of the desktop rail --
 * a duplicate Settings link with the badge chips, a Switch to admin
 * button and a theme row -- with one account row that opens a menu. The
 * drawer never came along, because the flag that gated the account
 * block was $railCard, which means "this surface is the inset desktop
 * card" and was quietly doing double duty.
 *
 * So on a phone the foot was still the pre-#553 layout, stacked and
 * clipping: "bottom avatar and settings menu how sit in sidebar in large
 * screen isnt in mobile screen... unneccessary space being hidden"
 * (Sana).
 */
class TheDrawerGetsTheSameAccountRowTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();

        return (string) $this->actingAs($user)->get('/user/dashboard')->assertOk()->getContent();
    }

    /** The drawer's own markup, which is where the regression lived. */
    private function drawer(string $html): string
    {
        $start = strpos($html, 'dash-glass navigation-mobile');
        $this->assertNotFalse($start, 'The mobile drawer did not render at all.');

        $end = strpos($html, '<main', $start);
        $this->assertNotFalse($end, 'The drawer never closed before <main>.');

        return substr($html, $start, $end - $start);
    }

    public function test_the_drawer_has_an_account_row_now(): void
    {
        $drawer = $this->drawer($this->page());

        $this->assertStringContainsString(
            'navrail-account',
            $drawer,
            'The drawer still has no account row, so its foot is the pre-#553 layout.'
        );
        $this->assertStringContainsString(
            'navrail-account-menu',
            $drawer,
            'The drawer has the row but not the menu it is supposed to open.'
        );
    }

    public function test_the_blocks_that_row_replaced_are_gone_from_the_drawer(): void
    {
        $html = $this->page();
        $drawer = $this->drawer($html);

        foreach ([
            'the badge chips'          => 'Your badges',
            'the admin switch button'  => 'admin-switch-btn',
        ] as $what => $needle) {
            $this->assertStringNotContainsString(
                $needle, $drawer, "{$what} is still stacked at the foot of the drawer."
            );
        }

        // The control. These must still exist SOMEWHERE on the page --
        // the classic sidebar keeps all of it -- or this test is passing
        // because the page failed to render rather than because the
        // drawer was tidied.
        $this->assertStringContainsString(
            'Your badges',
            $html,
            'The badge chips vanished from the whole page, so the check above proves nothing.'
        );
    }

    /**
     * The drawer is a column, so the block sits in flow. Pinned absolute
     * the way the card pins it, it would overlap the links above it.
     */
    public function test_the_drawer_places_the_row_in_flow_rather_than_pinning_it(): void
    {
        $html = $this->page();

        $this->assertMatchesRegularExpression(
            '/\.navrail-mobile \.navrail-account \{[^}]*position: static/s',
            $html,
            'The drawer pins the account row like the card does, which overlaps the links above it.'
        );

        // The control: the CARD must still pin it, or the desktop layout
        // is broken by the same change.
        $this->assertMatchesRegularExpression(
            '/\.navrail-account \{[^}]*position: absolute/s',
            $html,
            'The card no longer pins the account row to its foot.'
        );
    }
}
