<?php

namespace Tests\Feature;

use App\Services\Planner\FolderFlowMeasures;
use App\Services\Planner\FolderFlowStages;
use Tests\TestCase;

/**
 * The Flow tab used to carry one "Observed activity" line under the whole
 * chart. Putting a figure on a stage makes it look like the stage's figure,
 * so these guards are mostly about what each number does NOT cover.
 */
class FolderFlowMeasuresTest extends TestCase
{
    /** @return array{kind:string,row:object} */
    private function item(string $kind, array $attrs = []): array
    {
        return ['kind' => $kind, 'row' => (object) $attrs];
    }

    public function test_create_counts_clicks_on_the_folders_links(): void
    {
        $measures = FolderFlowMeasures::all([
            $this->item('link', ['total_clicks' => 120]),
            $this->item('link', ['total_clicks' => 30]),
        ]);

        $this->assertSame(150, $measures['create']['value']);
        $this->assertSame(['link'], $measures['create']['counted']);
    }

    /**
     * The double-count this class exists to stop.
     *
     * A QrCode belongs to a Link, so its scans are already that link's clicks.
     * Counting QR codes under Create would add the same event twice, and the
     * total would still look plausible.
     */
    public function test_a_qr_code_is_never_counted_under_create(): void
    {
        $measures = FolderFlowMeasures::all([
            $this->item('link', ['total_clicks' => 100]),
            $this->item('qr', ['total_clicks' => 100]),
        ]);

        $this->assertSame(100, $measures['create']['value'],
            "a QR code's scans are its link's clicks; counting both doubles one event");
        $this->assertContains('qr', $measures['create']['uncounted']);
    }

    public function test_reach_counts_buzz_impressions_and_act_counts_finished_tasks(): void
    {
        $measures = FolderFlowMeasures::all([
            $this->item('buzz', ['impressions' => 4000]),
            $this->item('buzz', ['impressions' => 250]),
            $this->item('task', ['completed_at' => '2026-10-01 10:00:00']),
            $this->item('task', ['completed_at' => null]),
            $this->item('task', ['completed_at' => null]),
        ]);

        $this->assertSame(4250, $measures['reach']['value']);
        $this->assertSame(1, $measures['act']['value'], 'one of the three tasks is done');
        $this->assertSame(3, $measures['act']['of']);
    }

    public function test_serve_counts_the_conversations_it_is_given(): void
    {
        $measures = FolderFlowMeasures::all([$this->item('companion', [])], 42);

        $this->assertSame(42, $measures['serve']['value']);
        $this->assertSame(['companion'], $measures['serve']['counted']);
    }

    /**
     * Zero is a measurement; null is the absence of one.
     *
     * Prepare holds files, mind maps, plans, budgets and brand kits, none of
     * which record an event. A 0 there would be a claim about the folder --
     * "nothing happened" -- when the truth is a claim about the software:
     * nothing here is measured.
     */
    public function test_a_stage_with_nothing_to_count_is_null_not_zero(): void
    {
        $measures = FolderFlowMeasures::all([]);

        $this->assertNull($measures['prepare'], 'Prepare records no events, so it must show no number at all');
        $this->assertSame(['prepare'], FolderFlowMeasures::unmeasuredStages($measures));

        $this->assertNotNull($measures['create'], 'a measured stage with no activity still reports its zero');
        $this->assertSame(0, $measures['create']['value']);
    }

    /** Every stage is accounted for, measured or not. */
    public function test_every_stage_has_an_entry(): void
    {
        $measures = FolderFlowMeasures::all([]);

        $this->assertSame(array_keys(FolderFlowStages::all()), array_keys($measures),
            'a stage missing from the measures would render no number and give no reason');
    }

    /**
     * The guard that keeps the second list honest.
     *
     * For every stage, the kinds a number covers plus the kinds it does not
     * must be exactly that stage's kinds. A kind added to FolderFlowStages
     * then lands in `uncounted` by itself, so the tab says it is not covered
     * rather than quietly implying the number includes it.
     */
    public function test_each_measure_accounts_for_every_kind_in_its_stage(): void
    {
        $measures = FolderFlowMeasures::all([]);

        foreach (FolderFlowStages::all() as $stageKey => $stage) {
            if ($measures[$stageKey] === null) {
                continue;
            }

            $accounted = array_merge($measures[$stageKey]['counted'], $measures[$stageKey]['uncounted']);
            sort($accounted);
            $kinds = $stage['kinds'];
            sort($kinds);

            $this->assertSame($kinds, $accounted,
                "stage {$stageKey}: every kind must be named as counted or as not counted, so the number's scope is never implied");
        }
    }
}
