<?php

namespace App\Services\Planner;

/**
 * What each Flow stage can honestly show a number for.
 *
 * The Flow tab carried one "Observed activity" line under the whole chart:
 * lifetime link clicks and Buzz impressions, for the folder as a whole. Those
 * are real figures, but sitting under five stages they read as if the stages
 * produced them, and neither one belongs to a stage the reader can identify.
 *
 * This puts each figure on the stage whose kind it came from -- and, more
 * importantly, records which kinds in that stage the figure does NOT cover.
 * That second list is the point. A bare "1,240" under Create, a stage holding
 * links, forms, QR codes and calendars, reads as all four. It is links only.
 *
 * Two rules this class exists to hold:
 *
 *  1. A stage with nothing countable returns NULL, never 0. Zero is a
 *     measurement ("nothing happened"); null is the absence of one ("nothing
 *     here is measured"). Prepare has no event data at all, and showing it a 0
 *     would be a claim about the folder rather than about the software.
 *
 *  2. QR codes are never counted under Create. A QrCode belongs to a Link
 *     (QrCode::link()), so a scan is already a click on that link. Adding
 *     "scans" to "clicks" double-counts the same event, and does it invisibly:
 *     the total still looks plausible.
 *
 * None of these numbers are attribution. A click is not caused by the Prepare
 * stage upstream of it; the rail's order is still only a suggestion, as
 * FolderFlowEdges and the tab's own copy say.
 */
class FolderFlowMeasures
{
    /**
     * @param  array<int,array{kind:string,row:object}>  $resources  the folder's items
     * @param  int  $companionConversations  conversations across this folder's
     *         companions, counted once by FolderWorkspace rather than per row
     * @return array<string, null|array{value:int,of:?int,suffix:string,counted:list<string>,uncounted:list<string>}>
     */
    public static function all(array $resources, int $companionConversations = 0): array
    {
        $of = fn (string $kind) => array_values(array_filter($resources, fn ($i) => ($i['kind'] ?? null) === $kind));
        $sum = fn (array $rows, string $field) => (int) array_sum(array_map(fn ($i) => (int) ($i['row']->{$field} ?? 0), $rows));

        $linkClicks = $sum($of('link'), 'total_clicks');
        $impressions = $sum($of('buzz'), 'impressions');

        $tasks = $of('task');
        $tasksDone = count(array_filter($tasks, fn ($i) => ! empty($i['row']->completed_at)));

        $measures = [
            // Files, mind maps, plans, budgets and brand kits record no events
            // of any kind. There is nothing to count, which is not the same as
            // counting nothing.
            'prepare' => null,

            'create' => self::measure($linkClicks, null, 'clicks on its links', 'create', ['link']),

            'serve' => self::measure($companionConversations, null, 'companion conversations', 'serve', ['companion']),

            'reach' => self::measure($impressions, null, 'Buzz impressions', 'reach', ['buzz']),

            'act' => self::measure($tasksDone, count($tasks), 'tasks done', 'act', ['task']),
        ];

        return $measures;
    }

    /**
     * Build one stage's measure, deriving the uncounted kinds from the stage
     * map rather than from a second hand-written list. A kind added to a stage
     * lands in `uncounted` on its own, so the tab says it is not covered
     * instead of quietly implying it is.
     *
     * @param  list<string>  $counted
     * @return array{value:int,of:?int,suffix:string,counted:list<string>,uncounted:list<string>}
     */
    private static function measure(int $value, ?int $of, string $suffix, string $stageKey, array $counted): array
    {
        $kinds = FolderFlowStages::all()[$stageKey]['kinds'] ?? [];

        return [
            'value'     => $value,
            'of'        => $of,
            'suffix'    => $suffix,
            'counted'   => array_values(array_intersect($kinds, $counted)),
            'uncounted' => array_values(array_diff($kinds, $counted)),
        ];
    }

    /** The stages that show no number at all. @return list<string> */
    public static function unmeasuredStages(array $measures): array
    {
        return array_values(array_keys(array_filter($measures, fn ($m) => $m === null)));
    }
}
