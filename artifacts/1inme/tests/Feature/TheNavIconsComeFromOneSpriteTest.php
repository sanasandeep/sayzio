<?php

namespace Tests\Feature;

use App\Modules\User\Models\User;
use App\Modules\User\Support\NavIcons;
use App\Modules\User\Support\NavRailSections;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The navigation's glyphs are sprite references, and every one of them
 * points at something.
 *
 * THE FAILURE THIS EXISTS TO CATCH
 *
 * A <use href="#i-something-that-is-not-there"> renders an EMPTY BOX.
 * No console error, no layout shift, nothing in a diff to notice --
 * exactly the shape of the .navrail-rail bug, where a complete and
 * well-formed stylesheet addressed a class the markup never had. So
 * the central assertion here resolves every reference on a real
 * rendered page against the symbols the sprite actually defines,
 * rather than asking whether the markup looks right.
 *
 * WHAT IS DELIBERATELY STILL FONT AWESOME
 *
 * A workspace's symbol is a stored database value and nothing
 * constrains what it can be, so no sprite can promise an id for it.
 * Mapping it would turn an unknown symbol into an empty box, which is
 * worse than an icon from a font that has every name.
 */
class TheNavIconsComeFromOneSpriteTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['onboarded_at' => now()->subMonths(6)])->fresh();
    }

    private function dashboard(): string
    {
        return (string) $this->actingAs($this->owner())
            ->get('/user/dashboard')->assertOk()->getContent();
    }

    /** @return array{0:array<string>,1:array<string>} [referenced, defined] */
    private function referencesAndSymbols(string $html): array
    {
        preg_match_all('/<use[^>]*href="#i-([a-z0-9-]+)"/', $html, $used);
        preg_match_all('/<symbol id="i-([a-z0-9-]+)"/', $html, $defined);

        return [array_values(array_unique($used[1])), array_values(array_unique($defined[1]))];
    }

    public function test_every_reference_on_the_page_resolves_to_a_symbol(): void
    {
        [$referenced, $defined] = $this->referencesAndSymbols($this->dashboard());

        $this->assertGreaterThan(
            30,
            count($referenced),
            'Too few references found; the nav did not render as sprite icons at all.'
        );
        $this->assertNotEmpty($defined, 'The sprite itself is missing from the page.');

        $dangling = array_values(array_diff($referenced, $defined));
        $this->assertSame([], $dangling, 'These render as empty boxes: ' . implode(', ', $dangling));
    }

    /**
     * The control. The check above passes trivially if the regex is
     * wrong in a way that finds nothing, so a reference that genuinely
     * is not there has to be caught.
     */
    public function test_a_reference_to_a_symbol_that_does_not_exist_would_be_caught(): void
    {
        [$referenced, $defined] = $this->referencesAndSymbols(
            $this->dashboard() . '<use href="#i-not-a-real-icon">'
        );

        $this->assertContains('not-a-real-icon', $referenced, 'The search cannot see a reference.');
        $this->assertSame(['not-a-real-icon'], array_values(array_diff($referenced, $defined)));
    }

    /**
     * The sprite has to cover everything the map can produce, including
     * the fallback -- otherwise an unmapped name resolves to a symbol
     * that is itself missing, and the hint disappears with it.
     */
    public function test_the_sprite_covers_every_symbol_the_map_can_produce(): void
    {
        [, $defined] = $this->referencesAndSymbols($this->dashboard());

        $missing = array_values(array_diff(NavIcons::symbols(), $defined));
        $this->assertSame([], $missing, 'The map can produce symbols the sprite lacks: ' . implode(', ', $missing));
        $this->assertContains(NavIcons::FALLBACK, $defined);
    }

    /**
     * The seven rail sections are the one set of glyphs that is mapped
     * at render time rather than rewritten in place, so a section added
     * later with an unmapped icon would quietly get the fallback.
     */
    public function test_every_rail_section_icon_is_mapped_rather_than_falling_back(): void
    {
        $fellBack = [];
        foreach (NavRailSections::SECTIONS as $key => $section) {
            if (NavIcons::lucide($section['icon']) === NavIcons::FALLBACK) {
                $fellBack[] = $key . ' (' . $section['icon'] . ')';
            }
        }

        $this->assertSame([], $fellBack, 'Rail sections with no mapped icon: ' . implode(', ', $fellBack));
    }

    public function test_the_map_tolerates_the_shapes_a_caller_might_pass(): void
    {
        $this->assertSame('house', NavIcons::lucide('fa-house'));
        $this->assertSame('house', NavIcons::lucide('fas fa-house'));
        $this->assertSame('house', NavIcons::lucide('fa-house text-xs'));
        $this->assertSame(NavIcons::FALLBACK, NavIcons::lucide('fa-not-a-thing'));
        $this->assertSame(NavIcons::FALLBACK, NavIcons::lucide(null));
    }

    /**
     * "make it svg icons... making it unique color effects" (Sana). The
     * colours were already there -- every row has carried its own
     * --nav-tint since the nav was built -- but only ever appeared on
     * hover, and on aurora not even then.
     */
    public function test_a_resting_icon_shows_its_own_tint(): void
    {
        $html = $this->dashboard();

        $this->assertMatchesRegularExpression(
            '/\.sidebar-link \.nav-icon-wrap \{\s*color: color-mix\(in srgb, var\(--nav-tint/s',
            $html,
            'Resting icons must carry their own tint, not one flat muted colour.'
        );
        // Aurora had muted every resting icon outright. The row text
        // still sits a step back; only the icon gains its colour.
        $this->assertMatchesRegularExpression(
            '/html\.aurora \.sidebar-link:not\(\.active\) \.nav-icon-wrap \{\s*color: color-mix\(in srgb, var\(--nav-tint/s',
            $html
        );
        // Stroke geometry belongs to the referencing element, because a
        // <use> clone does not inherit from the sprite.
        $this->assertMatchesRegularExpression(
            '/\.nav-icon \{[^}]*stroke: currentColor/s',
            $html
        );
    }

    /**
     * The conversion must not have dropped a glyph on the floor: the
     * only Font Awesome left in the desktop sidebar is the one that
     * cannot be mapped.
     */
    public function test_the_only_webfont_glyph_left_in_the_rail_is_the_one_that_cannot_be_mapped(): void
    {
        $html = $this->dashboard();
        $start = strpos($html, 'sidebar-v2 sidebar-shell');
        $this->assertNotFalse($start);
        $aside = substr($html, $start, strpos($html, '</aside>', $start) - $start);

        $this->assertGreaterThan(20, substr_count($aside, 'class="nav-icon'), 'The rail lost its icons.');

        preg_match_all('/class="fas ([a-z0-9- ]*)"/', $aside, $m);
        $left = array_values(array_filter(array_map('trim', $m[1])));
        $this->assertLessThanOrEqual(
            1,
            count($left),
            'Unconverted webfont glyphs in the rail: ' . implode(', ', $left)
        );
    }
}
