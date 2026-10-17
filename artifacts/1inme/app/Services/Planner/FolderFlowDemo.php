<?php

namespace App\Services\Planner;

/**
 * A worked example of the Flow map, for the "How it works" view.
 *
 * Sana, 2026-10-17: "demo should match similar.. but not empty things....
 * show some value.. increa values suitabley to look full fledged working....
 * the demo part will actually understand user what and why it will be
 * usefull".
 *
 * EVERY NUMBER HERE IS INVENTED. That is the point -- a new folder is empty,
 * and an empty map teaches nothing about what a full one would tell you. So
 * this is a fictional coffee roastery mid-launch, busy enough that every
 * station carries something and the lines have somewhere to go.
 *
 * It is also the one place in this tab where a figure is not measured, which
 * is why the sample is kept here rather than mixed into the real services:
 * nothing can read FolderFlowDemo by accident, the view has to ask for it by
 * name, and the view that asks for it puts a banner on the page saying so.
 * The sample names are given as initials against a fictional company so they
 * cannot be confused with workspace members.
 *
 * GEOMETRY AND ICONS COME FROM FolderFlowNetwork, not from here. The example
 * has to be the same map, or it teaches the wrong one -- so only the counts,
 * figures and people are invented, and a test holds the layout to matching.
 */
class FolderFlowDemo
{
    public const COMPANY = 'Northwind Coffee';
    public const FOLDER = 'Autumn launch';

    /** Busy enough that no station is empty: an empty example teaches nothing. */
    private const COUNTS = [
        'brand' => 3, 'budget' => 2, 'plan' => 5, 'mind' => 4, 'file' => 12,
        'link' => 24, 'form' => 6, 'qr' => 9, 'calendar' => 3,
        'campaign' => 7, 'studio' => 11, 'buzz' => 4, 'strategy' => 2,
        'companion' => 3, 'agent' => 2, 'staff' => 4, 'persona' => 5,
        'task' => 38, 'board' => 4,
    ];

    /** The figure a stage puts on its stations, worded as the real ones are. */
    private const FIGURES = [
        'link'      => ['value' => 1284, 'of' => null, 'suffix' => 'clicks on its links'],
        'form'      => ['value' => 1284, 'of' => null, 'suffix' => 'clicks on its links'],
        'qr'        => ['value' => 1284, 'of' => null, 'suffix' => 'clicks on its links'],
        'calendar'  => ['value' => 1284, 'of' => null, 'suffix' => 'clicks on its links'],
        'buzz'      => ['value' => 9640, 'of' => null, 'suffix' => 'Buzz impressions'],
        'companion' => ['value' => 212,  'of' => null, 'suffix' => 'companion conversations'],
        'task'      => ['value' => 26,   'of' => 38,   'suffix' => 'tasks done'],
        'board'     => ['value' => 26,   'of' => 38,   'suffix' => 'tasks done'],
    ];

    /** Initials against a fictional company, so nobody reads them as members. */
    private const PEOPLE = [
        ['id' => -1, 'name' => 'A. Rivera',  'changes' => 31],
        ['id' => -2, 'name' => 'N. Haddad',  'changes' => 18],
        ['id' => -3, 'name' => 'S. Tan',     'changes' => 12],
        ['id' => -4, 'name' => 'K. Mbeki',   'changes' => 5],
    ];

    /** Which sample people show on which station. */
    private const WORKED = [
        'link' => [0, 1, 2, 3], 'form' => [0, 2], 'qr' => [1],
        'task' => [0, 1, 2], 'board' => [1], 'campaign' => [0, 3],
        'studio' => [3], 'plan' => [0, 1], 'file' => [2], 'brand' => [3],
        'buzz' => [1], 'staff' => [0], 'persona' => [2],
    ];

    /** Saved item-to-item connections, as junction counts. */
    private const JUNCTIONS = [
        ['from' => 'link', 'to' => 'form', 'count' => 6],
        ['from' => 'link', 'to' => 'qr', 'count' => 4],
        ['from' => 'plan', 'to' => 'link', 'count' => 3],
        ['from' => 'campaign', 'to' => 'studio', 'count' => 5],
        ['from' => 'task', 'to' => 'campaign', 'count' => 2],
    ];

    /**
     * The same shape map() returns, so the view renders it with the same
     * markup and it cannot drift into looking like a different product.
     */
    public static function map(): array
    {
        $stations = [];
        foreach (FolderFlowNetwork::stations() as $kind => $place) {
            $stage = FolderFlowNetwork::lineFor($kind);
            $count = self::COUNTS[$kind] ?? 0;
            $stations[] = [
                'kind'    => $kind,
                'stage'   => $stage,
                'col'     => $place['col'],
                'row'     => $place['row'],
                'icon'    => $place['icon'],
                'count'   => $count,
                'in_use'  => $count > 0,
                'figure'  => self::FIGURES[$kind] ?? null,
                'ai'      => FolderFlowNetwork::isAi($kind),
                'tab'     => $stage ? (FolderFlowStages::all()[$stage]['tab'] ?? 'resources') : 'resources',
                'people'  => array_map(fn ($i) => self::PEOPLE[$i], self::WORKED[$kind] ?? []),
                'changes' => array_sum(array_map(fn ($i) => self::PEOPLE[$i]['changes'], self::WORKED[$kind] ?? [])),
            ];
        }

        return [
            'stations' => $stations,
            'edges'    => array_map(fn ($j) => $j + ['self' => false], self::JUNCTIONS),
            'rails'    => FolderFlowNetwork::rails(),
            'cols'     => FolderFlowNetwork::COLS,
            'rows'     => FolderFlowNetwork::ROWS,
        ];
    }

    /** Shaped like FolderKindActivity::data(), so the same markup reads it. */
    public static function activity(): array
    {
        $byKind = [];
        foreach (self::WORKED as $kind => $indexes) {
            $byKind[$kind] = [
                'people'  => array_map(fn ($i) => self::PEOPLE[$i], $indexes),
                'changes' => array_sum(array_map(fn ($i) => self::PEOPLE[$i]['changes'], $indexes)),
            ];
        }

        return [
            'byKind'            => $byKind,
            'people'            => self::PEOPLE,
            'counted'           => array_sum(array_column(self::PEOPLE, 'changes')),
            'owner_changes'     => 47,
            'unattributed'      => 0,
            'total'             => array_sum(array_column(self::PEOPLE, 'changes')) + 47,
            'window_days'       => FolderKindActivity::WINDOW_DAYS,
            'first_recorded_at' => null,
            'owner_name'        => 'the owner',
        ];
    }

    /**
     * What each mark on the map means. These used to sit under the real map
     * as four paragraphs of prose that every user read once and then scrolled
     * past forever; Sana asked for them here instead, with the real map
     * keeping only what is true of that particular folder.
     *
     * @return list<array{title:string,body:string}>
     */
    public static function legend(): array
    {
        return [
            [
                'title' => 'A station is a kind of thing',
                'body'  => 'Every kind a folder can hold has a station, whether or not this folder uses it. The big number is how many it holds. An unused station stays on the map, dashed, so you can see what is available rather than only what you already started.',
            ],
            [
                'title' => 'The small figure is measured',
                'body'  => 'Under the count, a station may show a figure its stage actually measures -- clicks for content, impressions for Buzz, conversations for channels, completion for tasks. A station whose stage measures nothing says "not counted here" rather than showing a zero that would read as a result.',
            ],
            [
                'title' => 'Rails are a suggested order, not a record',
                'body'  => 'The coloured lines are one sensible way work moves through a folder: prepare, create, reach and serve the people it brings in, and act on what comes back. They are a suggestion. Nothing on the map claims the work happened in that order.',
            ],
            [
                'title' => 'A junction is real',
                'body'  => 'The numbered circle where two lines meet is the count of item-to-item connections somebody actually saved between those two kinds, under Resources. Unlike the rails, a junction is a record of something that exists.',
            ],
            [
                'title' => 'A rounder station runs on AI',
                'body'  => 'Eight of the nineteen kinds are built on AI. They are drawn with rounder corners and a round icon, so you can see at a glance how much of a folder leans on it.',
            ],
            [
                'title' => 'Faces are who changed something',
                'body'  => 'Up to three people who changed anything of that kind in this folder recently, most active first. The workspace owner is left out on purpose -- they are in every folder, so counting them says nothing about who else is involved.',
            ],
            [
                'title' => 'The filters narrow the question',
                'body'  => 'Hide what is unused, switch whole lines off, separate AI from the rest, or show only what one person has touched. Hidden stations leave their space empty and their rails disappear, so the map never redraws itself into a different shape.',
            ],
        ];
    }
}
