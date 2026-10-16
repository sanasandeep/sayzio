<?php

namespace Tests\Feature;

use App\Services\Planner\FolderFlowStages;
use App\Services\Planner\PlannerResources;
use Tests\TestCase;

/**
 * The folder Flow tab groups a folder's resources into stages.
 *
 * Before this guard the stage map was a literal array inside one line of
 * folder-map.blade.php, and six of the nineteen kinds in
 * PlannerResources::TYPES were in no stage at all: calendars, task boards,
 * personas, AI staff, agents and channels. Each of those can be assigned to a
 * folder, each appeared in $resources, and each was counted by nothing on the
 * tab. No test failed, because nothing compared the two lists.
 *
 * That is the same shape as the plan-category bug: one place knows what kinds
 * exist, another place has to agree, and nothing checks.
 */
class FolderFlowMapTest extends TestCase
{
    public function test_every_resource_kind_a_folder_can_hold_belongs_to_a_stage(): void
    {
        $placed = FolderFlowStages::placedKinds();

        $missing = [];
        foreach (array_keys(PlannerResources::TYPES) as $kind) {
            if (in_array($kind, FolderFlowStages::UNPLACED, true)) {
                continue;
            }
            if (! in_array($kind, $placed, true)) {
                $missing[] = $kind;
            }
        }

        sort($missing);
        $this->assertSame([], $missing, 'these resource kinds can be assigned to a folder but appear in no Flow stage, so they are counted nowhere: '.implode(', ', $missing));
    }

    /**
     * A kind in two stages would be counted twice, and the two totals would
     * disagree with the folder's own item count for no visible reason.
     */
    public function test_no_kind_is_claimed_by_two_stages(): void
    {
        $placed = FolderFlowStages::placedKinds();
        $duplicates = array_values(array_unique(array_diff_assoc($placed, array_unique($placed))));

        sort($duplicates);
        $this->assertSame([], $duplicates, 'these kinds appear in more than one Flow stage and would be counted twice: '.implode(', ', $duplicates));
    }

    /**
     * Every stage points somewhere. A node whose tab does not exist is a
     * dead click, and the stage map is the only thing that decides it.
     */
    public function test_every_stage_points_at_a_real_folder_tab(): void
    {
        $knownTabs = ['resources', 'links', 'marketing', 'tasks', 'remember', 'reports'];

        foreach (FolderFlowStages::all() as $key => $stage) {
            $this->assertNotSame('', trim($stage['eyebrow']), "stage {$key} has no eyebrow");
            $this->assertNotSame('', trim($stage['title']), "stage {$key} has no title");
            $this->assertNotEmpty($stage['kinds'], "stage {$key} groups no kinds, so it always reads zero");
            $this->assertContains($stage['tab'], $knownTabs, "stage {$key} links to the unknown folder tab '{$stage['tab']}'");
        }
    }

    /**
     * Anything excluded on purpose needs a reason someone wrote down. An
     * empty allowlist is the healthy state; a growing one without comments is
     * how the original six went missing.
     */
    public function test_unplaced_kinds_are_real_kinds(): void
    {
        foreach (FolderFlowStages::UNPLACED as $kind) {
            $this->assertArrayHasKey($kind, PlannerResources::TYPES, "'{$kind}' is excluded from the Flow stages but is not a resource kind at all");
        }
    }
}
