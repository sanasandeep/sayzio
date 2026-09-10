<?php

namespace Tests\Feature;

use App\Modules\Common\Support\ComparisonContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage's compare band.
 *
 * It used to be a heading and a single button: a claim ("more features,
 * better deal") followed by no evidence for it. The panel that replaced it
 * derives everything it says from ComparisonContent -- the scores, which
 * rival is closest, and which features nothing else on the list has.
 *
 * That derivation is the thing worth guarding. Marketing copy that repeats a
 * number goes stale silently the moment a competitor ships something; these
 * assertions fail loudly instead.
 */
class HomepageCompareTeaserTest extends TestCase
{
    use RefreshDatabase;

    public function test_teaser_quotes_the_score_and_the_closest_rival_from_the_data(): void
    {
        $scores = ComparisonContent::scores();
        $total  = ComparisonContent::totalFeatures();

        $rivals = [];
        foreach (array_slice(ComparisonContent::competitors(), 1) as $r) {
            $rivals[$r['name']] = (int) ($scores[$r['key']] ?? 0);
        }
        arsort($rivals);
        $bestName  = array_key_first($rivals);
        $bestScore = $rivals[$bestName];

        $this->get('/home/sections')
            ->assertOk()
            ->assertSee($scores['ours'] . ' of ' . $total)
            ->assertSee('The closest tool on the list, ' . $bestName . ', does ' . $bestScore . '.');
    }

    public function test_teaser_lists_exactly_the_features_no_rival_has(): void
    {
        $exclusive = [];
        foreach (ComparisonContent::featuresFlat() as [$name, $matrix]) {
            if (empty($matrix['ours'])) {
                continue;
            }
            if (count(array_filter(array_diff_key($matrix, ['ours' => 1]))) === 0) {
                $exclusive[] = $name;
            }
        }

        $this->assertNotEmpty($exclusive, 'The panel has nothing to show if this is empty.');

        $res = $this->get('/home/sections')->assertOk();
        foreach ($exclusive as $name) {
            $res->assertSee($name);
        }
        $res->assertSee(count($exclusive) . ' of them, nobody else has at all');
    }

    /**
     * Every bar is a percentage of the same total the button leads to, so a
     * width over 100% would mean the two disagree.
     */
    public function test_no_scoreboard_bar_exceeds_the_total(): void
    {
        $total = ComparisonContent::totalFeatures();
        foreach (ComparisonContent::scores() as $key => $score) {
            $this->assertLessThanOrEqual(
                $total,
                $score,
                "{$key} scores {$score} against a total of {$total}."
            );
        }
    }

    public function test_teaser_still_points_at_the_full_comparison(): void
    {
        $this->get('/home/sections')
            ->assertOk()
            ->assertSee('See all ' . ComparisonContent::totalFeatures() . ' side by side')
            ->assertSee('/pricing#compare', false);
    }
}
