<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chrome in India reports `Asia/Calcutta`. The timezone select is built
 * from PHP's timezone_identifiers_list(), which offers `Asia/Kolkata`
 * and not `Asia/Calcutta`. Chrome's own
 * Intl.supportedValuesOf('timeZone') contains `Asia/Calcutta` and NOT
 * `Asia/Kolkata`.
 *
 * So the browser and the server use different canonical names for the
 * same zone, and neither side will convert: ICU treats the backward name
 * as canonical, IANA and PHP treat the modern one as canonical. Without
 * a mapping, detection finds no matching option and silently does
 * nothing -- making the feature a no-op for most Indian users, who are
 * the ones it was built for.
 *
 * The map in user/partials/timezone-autodetect.blade.php bridges that.
 * These tests hold its two invariants, because a tzdata update can move
 * a name from one side to the other and the failure is silent:
 *
 *   - every KEY must be absent from the options, or the entry is dead
 *     weight that will never be consulted;
 *   - every VALUE must be present in the options, or the mapping points
 *     at something the control cannot select.
 */
class BrowserTimezoneAliasesResolveToRealOptionsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,string> alias => canonical, read from the partial itself */
    private function aliasMap(): array
    {
        $src = file_get_contents(resource_path('views/user/partials/timezone-autodetect.blade.php'));
        $this->assertNotFalse($src);

        $this->assertTrue(
            (bool) preg_match('~var ALIASES = \{(.*?)\};~s', $src, $block),
            'no ALIASES map found in the autodetect partial'
        );

        preg_match_all("~'([^']+)'\s*:\s*'([^']+)'~", $block[1], $pairs, PREG_SET_ORDER);

        $map = [];
        foreach ($pairs as $p) {
            $map[$p[1]] = $p[2];
        }

        return $map;
    }

    public function test_the_map_is_not_empty_and_covers_india(): void
    {
        $map = $this->aliasMap();

        $this->assertGreaterThan(10, count($map), 'the alias map parsed to almost nothing');

        // The case that prompted all of this.
        $this->assertSame('Asia/Kolkata', $map['Asia/Calcutta'] ?? null);
    }

    public function test_every_alias_is_one_the_options_really_lack(): void
    {
        $options = timezone_identifiers_list();

        foreach ($this->aliasMap() as $alias => $canonical) {
            $this->assertNotContains(
                $alias,
                $options,
                "{$alias} is already offered by the select, so mapping it is dead weight — drop the entry"
            );
        }
    }

    public function test_every_alias_points_at_an_option_that_exists(): void
    {
        $options = timezone_identifiers_list();

        foreach ($this->aliasMap() as $alias => $canonical) {
            $this->assertContains(
                $canonical,
                $options,
                "{$alias} maps to {$canonical}, which the select does not offer — the control could never take it"
            );
        }
    }

    public function test_the_new_event_form_ships_the_map(): void
    {
        // The partial is pushed onto the scripts stack, so it is possible
        // for the map to exist and never reach the page.
        $user = User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();

        $html = (string) $this->actingAs($user)
            ->get(route('user.links.ics.create', [], false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Asia/Calcutta', $html, 'the alias map did not reach the page');
        $this->assertStringContainsString('data-tz-autodetect', $html);
    }
}
