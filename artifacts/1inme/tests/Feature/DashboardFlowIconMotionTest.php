<?php

namespace Tests\Feature;

use App\Services\Planner\FolderFlowNetwork;
use App\Services\Planner\PlannerResources;
use Tests\TestCase;

/**
 * Each station icon on the dashboard has its own piece of motion.
 *
 * The icons are shared constants -- FolderFlowNetwork::stations() draws the
 * same glyphs on the folder Flow tab -- so their inner shapes carry no classes
 * and the animations address them by position: nth-child(2), nth-child(3).
 *
 * That is the arrangement this guard exists for. An index past the end of a
 * glyph animates nothing at all, and nothing anywhere fails: the icon simply
 * sits still while every other one moves, and you would only ever find it by
 * hovering all nineteen. Reordering a glyph's shapes on the Flow tab has the
 * same effect at a distance -- the robot's eyes stop blinking and its ear
 * starts to, with no edit to this file and no test failing.
 *
 * So: every kind must own at least one rule, and every index a rule names must
 * exist in that kind's glyph.
 */
class DashboardFlowIconMotionTest extends TestCase
{
    private const STYLES = 'views/user/partials/dashboard-flow-styles.blade.php';

    /** Top-level drawable elements in a glyph, in document order. */
    private function shapeCount(string $icon): int
    {
        return preg_match_all('/<(path|circle|rect|ellipse|line|polyline|polygon)\b/i', $icon);
    }

    /** @return list<array{kind: string, nth: int}> */
    private function rules(): array
    {
        $css = file_get_contents(resource_path(self::STYLES));
        $css = preg_replace('#/\*[\s\S]*?\*/#', '', $css);

        preg_match_all(
            '/\[data-kind=["\']?([a-z]+)["\']?\][^{]*?nth-child\((\d+)\)/i',
            $css,
            $m,
            PREG_SET_ORDER
        );

        return array_map(fn ($hit) => ['kind' => $hit[1], 'nth' => (int) $hit[2]], $m);
    }

    public function test_every_station_icon_has_motion_of_its_own(): void
    {
        $covered = array_unique(array_column($this->rules(), 'kind'));

        $still = array_values(array_diff(array_keys(PlannerResources::TYPES), $covered));
        sort($still);

        $this->assertSame([], $still, 'these station icons have no animation rule, so they sit still while every other icon moves: '.implode(', ', $still));
    }

    public function test_no_animation_points_past_the_end_of_its_icon(): void
    {
        $stations = FolderFlowNetwork::stations();

        $dangling = [];
        foreach ($this->rules() as $rule) {
            $shapes = $this->shapeCount($stations[$rule['kind']]['icon'] ?? '');
            if ($rule['nth'] > $shapes || $shapes === 0) {
                $dangling[] = "{$rule['kind']} nth-child({$rule['nth']}) but the glyph has {$shapes} shapes";
            }
        }

        sort($dangling);
        $this->assertSame([], $dangling, 'these animations address a shape that does not exist, so they animate nothing: '.implode('; ', $dangling));
    }

    /**
     * The motion is hover and focus only. Ambient loops on nineteen icons would
     * make a page people open daily unusable, and this is the kind of thing
     * that gets relaxed one rule at a time.
     */
    public function test_icon_motion_is_not_ambient(): void
    {
        $css = file_get_contents(resource_path(self::STYLES));
        $css = preg_replace('#/\*[\s\S]*?\*/#', '', $css);

        $ambient = [];
        foreach (preg_split('/(?<=\})/', $css) as $chunk) {
            if (! preg_match('/\[data-kind=/', $chunk) || ! preg_match('/animation\s*:/', $chunk)) {
                continue;
            }
            if (! str_contains($chunk, ':hover') && ! str_contains($chunk, ':focus-visible')) {
                $ambient[] = trim(explode('{', $chunk)[0]);
            }
        }

        sort($ambient);
        $this->assertSame([], $ambient, 'these icon animations run without hover or focus, so they loop forever on the dashboard: '.implode(', ', $ambient));
    }
}
