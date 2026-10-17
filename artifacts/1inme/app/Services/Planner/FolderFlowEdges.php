<?php

namespace App\Services\Planner;

/**
 * Sorts a folder's recorded item-to-item connections onto the Flow rail.
 *
 * The Flow tab draws five stages left to right with a connector between
 * each pair. Until now every connector was dashed and meant nothing: the
 * rail was a suggested order drawn the same way whether or not anyone had
 * ever connected two items in this folder. Meanwhile `folder_relationships`
 * -- rows a user saved by hand under Resources -- was loaded by
 * FolderWorkspace, listed three-at-a-time further down the page, and drawn
 * nowhere.
 *
 * This class maps each relationship's two endpoints to their stages and
 * files it in exactly one of four places, so a connector can say whether it
 * is carrying real connections or only a suggestion.
 *
 * The important property is CONSERVATION: every relationship handed in comes
 * out in exactly one bucket, and `total` equals the sum of them. A rail that
 * silently drops the connections it cannot draw would be worse than the
 * dashed line it replaces, because it would look measured.
 */
class FolderFlowEdges
{
    /**
     * @param  iterable<object>  $relations  rows from `folder_relationships`,
     *                                       each with source_kind / target_kind.
     * @return array{
     *     rail: array<string,int>,
     *     inside: array<string,int>,
     *     offRail: list<array{from:string,to:string,count:int,backwards:bool}>,
     *     unplaced: int,
     *     total: int,
     *     recorded: int
     * }
     */
    public static function from(iterable $relations): array
    {
        $order = array_keys(FolderFlowStages::all());
        $index = array_flip($order);

        // Seeded with every adjacent pair so the view can walk the rail
        // without worrying about which ones happen to have connections.
        $rail = [];
        foreach (self::railPairs() as [$from, $to]) {
            $rail[self::railKey($from, $to)] = 0;
        }

        $inside   = array_fill_keys($order, 0);
        $offRail  = [];
        $unplaced = 0;
        $total    = 0;

        foreach ($relations as $relation) {
            $total++;

            $from = FolderFlowStages::stageFor((string) ($relation->source_kind ?? ''));
            $to   = FolderFlowStages::stageFor((string) ($relation->target_kind ?? ''));

            // A kind in no stage. Should be impossible -- FolderFlowMapTest
            // holds every kind in PlannerResources::TYPES to a stage -- but a
            // relationship pointing at a kind that has since been removed
            // would land here rather than vanish.
            if ($from === null || $to === null) {
                $unplaced++;
                continue;
            }

            if ($from === $to) {
                $inside[$from]++;
                continue;
            }

            $key = self::railKey($from, $to);
            if (array_key_exists($key, $rail)) {
                $rail[$key]++;
                continue;
            }

            // Real, saved, and not drawable on a left-to-right rail: it skips
            // a stage or runs against the flow. Kept and surfaced as a count
            // rather than discarded.
            $offRail[$key] ??= ['from' => $from, 'to' => $to, 'count' => 0, 'backwards' => $index[$to] < $index[$from]];
            $offRail[$key]['count']++;
        }

        return [
            'rail'     => $rail,
            'inside'   => array_filter($inside),
            'offRail'  => array_values($offRail),
            'unplaced' => $unplaced,
            'total'    => $total,
            'recorded' => $total - $unplaced,
        ];
    }

    /**
     * The adjacent stage pairs the rail draws, in reading order.
     *
     * @return list<array{0:string,1:string}>
     */
    public static function railPairs(): array
    {
        $order = array_keys(FolderFlowStages::all());
        $pairs = [];

        for ($i = 0; $i < count($order) - 1; $i++) {
            $pairs[] = [$order[$i], $order[$i + 1]];
        }

        return $pairs;
    }

    public static function railKey(string $from, string $to): string
    {
        return $from.'>'.$to;
    }

    /**
     * How many saved connections sit between these two stages, in this
     * direction. Zero means the connector is a suggestion and nothing more.
     */
    public static function recordedBetween(array $edges, string $from, string $to): int
    {
        return $edges['rail'][self::railKey($from, $to)] ?? 0;
    }

    /** Total saved connections the rail could not draw. */
    public static function offRailCount(array $edges): int
    {
        return array_sum(array_column($edges['offRail'], 'count'));
    }
}
