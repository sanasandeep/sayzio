<?php

namespace App\Services\Planner;

/**
 * The Flow tab as a network map rather than a left-to-right rail.
 *
 * Sana, 2026-10-17, holding up a metro map: "i dont want it like path tab...
 * i want like network map... points i want like blocks with which active and
 * count and other info."
 *
 * The rail could only say one thing: five stages in a row. A folder is not a
 * queue. A transit map says more with the same ink — every kind is a station,
 * every stage is a line through its own stations, and the junctions are the
 * connections somebody actually saved.
 *
 * THE GEOMETRY IS FIXED, BY HAND, AND THAT IS THE POINT. Station coordinates
 * are constants here, never computed from the data. A force-directed layout
 * over nineteen nodes gives a hairball, and worse, it rearranges itself every
 * time the folder changes — a map whose stations move is not a map, because
 * you can never learn where anything is. Data changes what a station SAYS,
 * never where it sits.
 *
 * The shape: Prepare runs down the left as a spine and turns east into the
 * Create trunk; Reach and Serve branch off the QR interchange, above and
 * below; Act terminates on the right. Everything else the view draws — the
 * curves, the colours, the icons — is detail on top of this table.
 */
class FolderFlowNetwork
{
    /** Grid the coordinates below are expressed in. */
    public const COLS = 8;
    public const ROWS = 5;

    /**
     * Every station: its grid cell and the icon it is drawn with.
     *
     * Icons are stroke paths on a 24x24 box, so they take their line's colour
     * from `currentColor` and cost no request. Keep them stroked, not filled,
     * or a station will go solid black in dark mode.
     *
     * @return array<string, array{col:int,row:int,icon:string}>
     */
    public static function stations(): array
    {
        return [
            // Prepare — the spine, top to bottom on the left.
            'brand'     => ['col' => 1, 'row' => 1, 'icon' => '<path d="M12 3a9 9 0 1 0 0 18c1.1 0 1.8-.8 1.8-1.7 0-.5-.2-.9-.5-1.2-.3-.3-.5-.7-.5-1.1 0-.9.8-1.7 1.8-1.7H17a4 4 0 0 0 4-4c0-4.4-4-8.3-9-8.3z"/><circle cx="7.5" cy="11" r="1.1"/><circle cx="11" cy="7.5" r="1.1"/><circle cx="15.5" cy="9" r="1.1"/>'],
            'budget'    => ['col' => 1, 'row' => 2, 'icon' => '<ellipse cx="12" cy="6.5" rx="7" ry="3"/><path d="M5 6.5v11c0 1.7 3.1 3 7 3s7-1.3 7-3v-11"/><path d="M5 12c0 1.7 3.1 3 7 3s7-1.3 7-3"/>'],
            'plan'      => ['col' => 1, 'row' => 3, 'icon' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3h6v3H9z"/><path d="M8.5 11l1.6 1.6 3.4-3.4M8.5 16.5l1.6 1.6 3.4-3.4"/>'],
            'mind'      => ['col' => 1, 'row' => 4, 'icon' => '<circle cx="12" cy="12" r="2.6"/><circle cx="5" cy="6" r="2"/><circle cx="19" cy="6" r="2"/><circle cx="5" cy="18" r="2"/><circle cx="19" cy="18" r="2"/><path d="M6.6 7.4 10 10.4M17.4 7.4 14 10.4M6.6 16.6 10 13.6M17.4 16.6 14 13.6"/>'],
            'file'      => ['col' => 1, 'row' => 5, 'icon' => '<path d="M14 3H7a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1V7z"/><path d="M14 3v4h4"/><path d="M9 13h6M9 17h4"/>'],

            // Create — the trunk, running east through the middle.
            'link'      => ['col' => 2, 'row' => 3, 'icon' => '<path d="M10 13.5a4 4 0 0 0 5.7.4l2.6-2.6a4 4 0 0 0-5.7-5.7l-1.5 1.5"/><path d="M14 10.5a4 4 0 0 0-5.7-.4L5.7 12.7a4 4 0 0 0 5.7 5.7l1.5-1.5"/>'],
            'form'      => ['col' => 3, 'row' => 3, 'icon' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h4"/>'],
            'qr'        => ['col' => 4, 'row' => 3, 'icon' => '<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><path d="M14 14h3v3h-3zM19 14h1M14 19h3M19 18v2"/>'],
            'calendar'  => ['col' => 5, 'row' => 3, 'icon' => '<rect x="3.5" y="5" width="17" height="16" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/><circle cx="9" cy="15" r="1"/><circle cx="15" cy="15" r="1"/>'],

            // Reach — branches up off the QR interchange.
            'campaign'  => ['col' => 3, 'row' => 1, 'icon' => '<path d="M4 10v4a1 1 0 0 0 1 1h3l6 4V5l-6 4H5a1 1 0 0 0-1 1z"/><path d="M17.5 9a4 4 0 0 1 0 6M20 6.5a7.5 7.5 0 0 1 0 11"/>'],
            'studio'    => ['col' => 4, 'row' => 1, 'icon' => '<path d="M12 3.5 14.2 9l5.8.3-4.5 3.7 1.5 5.6L12 15.5l-5 3.1 1.5-5.6L4 9.3 9.8 9z"/>'],
            'buzz'      => ['col' => 5, 'row' => 1, 'icon' => '<circle cx="12" cy="12" r="2.2"/><path d="M8.4 8.4a5 5 0 0 0 0 7.2M15.6 15.6a5 5 0 0 0 0-7.2"/><path d="M5.6 5.6a9 9 0 0 0 0 12.8M18.4 18.4a9 9 0 0 0 0-12.8"/>'],
            'strategy'  => ['col' => 6, 'row' => 1, 'icon' => '<circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="4.6"/><circle cx="12" cy="12" r="1.2"/><path d="M12 3.5v2M12 18.5v2M3.5 12h2M18.5 12h2"/>'],

            // Serve — branches down off the same interchange.
            'companion' => ['col' => 3, 'row' => 5, 'icon' => '<path d="M20 14a3 3 0 0 1-3 3H9l-4 3.5V7a3 3 0 0 1 3-3h9a3 3 0 0 1 3 3z"/><path d="M12.5 8l.8 1.9 1.9.8-1.9.8-.8 1.9-.8-1.9-1.9-.8 1.9-.8z"/>'],
            'agent'     => ['col' => 4, 'row' => 5, 'icon' => '<rect x="4" y="8" width="16" height="11" rx="3"/><path d="M12 4v4M8.5 8V6.5M15.5 8V6.5"/><circle cx="9" cy="13" r="1.1"/><circle cx="15" cy="13" r="1.1"/><path d="M9.5 16h5"/>'],
            'staff'     => ['col' => 5, 'row' => 5, 'icon' => '<circle cx="9" cy="8" r="3"/><path d="M3.5 19.5a5.5 5.5 0 0 1 11 0"/><circle cx="17" cy="9.5" r="2.4"/><path d="M15 15.5a4.6 4.6 0 0 1 5.6 3.4"/>'],
            'persona'   => ['col' => 6, 'row' => 5, 'icon' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2.5"/><circle cx="9.5" cy="10.5" r="2.3"/><path d="M6 16.6a3.8 3.8 0 0 1 7 0"/><path d="M15.5 9.5h3M15.5 13h3"/>'],

            // Act — the two terminals on the right.
            'task'      => ['col' => 8, 'row' => 2, 'icon' => '<circle cx="12" cy="12" r="8.5"/><path d="M8.2 12.3l2.6 2.6 5-5.4"/>'],
            'board'     => ['col' => 8, 'row' => 4, 'icon' => '<rect x="3.5" y="4.5" width="17" height="15" rx="2"/><path d="M9.2 4.5v15M14.8 4.5v15"/><path d="M5.6 8h1.8M11.2 8h1.8M16.8 8h1.8"/>'],
        ];
    }

    /**
     * The rails, as runs of waypoints the view curves through.
     *
     * A waypoint is a station key, or ['x' => a, 'y' => b] for a corner that
     * takes its column from one station and its row from another. Every rail
     * belongs to a stage, so it is drawn in that stage's colour.
     *
     * @return list<array{stage:string, points:list<string|array{x:string,y:string}>}>
     */
    public static function rails(): array
    {
        return [
            ['stage' => 'prepare', 'points' => ['brand', 'budget', 'plan', 'mind', 'file']],
            ['stage' => 'prepare', 'points' => ['plan', 'link']],
            ['stage' => 'create',  'points' => ['link', 'form', 'qr', 'calendar']],
            ['stage' => 'reach',   'points' => ['campaign', 'studio', 'buzz', 'strategy']],
            ['stage' => 'reach',   'points' => ['qr', ['x' => 'qr', 'y' => 'studio']]],
            ['stage' => 'serve',   'points' => ['companion', 'agent', 'staff', 'persona']],
            ['stage' => 'serve',   'points' => ['qr', ['x' => 'qr', 'y' => 'agent']]],
            ['stage' => 'act',     'points' => ['calendar', ['x' => 'task', 'y' => 'calendar'], 'task']],
            ['stage' => 'act',     'points' => ['calendar', ['x' => 'board', 'y' => 'calendar'], 'board']],
            ['stage' => 'act',     'points' => ['task', 'board']],
        ];
    }

    /** Which stage's line a kind sits on, or null when it has no station. */
    public static function lineFor(string $kind): ?string
    {
        return FolderFlowStages::stageFor($kind);
    }

    /**
     * The map, ready to draw.
     *
     * @param  array<int,array{kind:string,row:object}>  $resources
     * @param  iterable<object>  $relations
     * @param  array<string,mixed>  $measures  FolderFlowMeasures::all(...)
     * @return array{stations:list<array>, edges:list<array>, rails:list<array>, cols:int, rows:int}
     */
    public static function map(array $resources, iterable $relations, array $measures): array
    {
        $counts = [];
        foreach ($resources as $item) {
            $kind = $item['kind'] ?? null;
            if ($kind !== null) {
                $counts[$kind] = ($counts[$kind] ?? 0) + 1;
            }
        }

        // A station shows a figure only when some measure counts that kind.
        // Derived from the measures, never listed again here, so a station can
        // never claim a number the stage says it is not part of.
        $figureFor = [];
        foreach ($measures as $measure) {
            if ($measure === null) {
                continue;
            }
            foreach ($measure['counted'] as $kind) {
                $figureFor[$kind] = [
                    'value'  => $measure['value'],
                    'of'     => $measure['of'],
                    'suffix' => $measure['suffix'],
                ];
            }
        }

        $stations = [];
        foreach (self::stations() as $kind => $place) {
            $stage = self::lineFor($kind);
            $count = $counts[$kind] ?? 0;
            $stations[] = [
                'kind'   => $kind,
                'stage'  => $stage,
                'col'    => $place['col'],
                'row'    => $place['row'],
                'icon'   => $place['icon'],
                'count'  => $count,
                'in_use' => $count > 0,
                'figure' => $figureFor[$kind] ?? null,
                'tab'    => $stage ? (FolderFlowStages::all()[$stage]['tab'] ?? 'resources') : 'resources',
            ];
        }

        return [
            'stations' => $stations,
            'edges'    => self::edges($relations),
            'rails'    => self::rails(),
            'cols'     => self::COLS,
            'rows'     => self::ROWS,
        ];
    }

    /**
     * Saved connections aggregated kind-to-kind — the junctions.
     *
     * FolderFlowEdges aggregates the same rows to STAGE level for the figures.
     * The map needs them one level finer, and keeps the pairs it cannot place
     * so the note under the map can say how many are not drawn, rather than
     * the map quietly showing fewer connections than the folder holds.
     *
     * @return list<array{from:?string,to:?string,count:int,self:bool}>
     */
    public static function edges(iterable $relations): array
    {
        $places = self::stations();
        $pairs = [];
        $undrawable = 0;

        foreach ($relations as $relation) {
            $from = (string) ($relation->source_kind ?? '');
            $to   = (string) ($relation->target_kind ?? '');

            if (! isset($places[$from], $places[$to])) {
                $undrawable++;
                continue;
            }

            $key = $from.'>'.$to;
            $pairs[$key] ??= ['from' => $from, 'to' => $to, 'count' => 0, 'self' => $from === $to];
            $pairs[$key]['count']++;
        }

        $edges = array_values($pairs);
        if ($undrawable > 0) {
            $edges[] = ['from' => null, 'to' => null, 'count' => $undrawable, 'self' => false];
        }

        return $edges;
    }

    /** Connections the map could not place on a station. */
    public static function undrawableCount(array $edges): int
    {
        foreach ($edges as $edge) {
            if ($edge['from'] === null) {
                return $edge['count'];
            }
        }

        return 0;
    }

    /** Connections the map does draw. */
    public static function drawnCount(array $edges): int
    {
        $n = 0;
        foreach ($edges as $edge) {
            if ($edge['from'] !== null) {
                $n += $edge['count'];
            }
        }

        return $n;
    }
}
