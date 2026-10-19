<?php

namespace App\Services\Planner;

/**
 * A worked example of the Flow map, for the "How it works" view.
 *
 * Sana, 2026-10-17: "demo should match similar.. but not empty things....
 * show some value.. increa values suitabley to look full fledged working".
 * Then, 2026-10-18, on seeing it: "here in demo, i need popup, lines both
 * working pending and.. some assign options.. kep 1-2 options empty also....
 * need demo data... that way it will explain even better".
 *
 * So the example now teaches the whole map, not only a busy one: stations
 * that open, items inside them, both kinds of line, and -- deliberately --
 * two stations left empty, because the dashed station and its "+ Assign"
 * are part of what a new folder looks like. A folder where everything is
 * full explains everything except the state every folder starts in.
 *
 * EVERY NUMBER AND NAME HERE IS INVENTED: a fictional coffee roastery
 * mid-launch. It is the one place in this tab where a figure is not
 * measured, which is why it lives apart from the real services -- nothing
 * can read it by accident, the view has to ask for it by name, and the view
 * that asks puts a band on the page saying so. Sample people are initials
 * against that fictional company with negative ids, so they cannot be
 * confused with, or matched against, a workspace member.
 *
 * GEOMETRY AND ICONS COME FROM FolderFlowNetwork. The example has to be the
 * same map or it teaches the wrong one; only counts, figures, items and
 * people are invented, and a test holds the layout to matching.
 */
class FolderFlowDemo
{
    public const COMPANY = 'Northwind Coffee';
    public const FOLDER = 'Autumn launch';

    /**
     * Two kinds are deliberately at zero. A reader who only ever sees a full
     * map never learns what the dashed station means, or where "+ Assign"
     * lives -- which is the state their own folder is actually in.
     *
     * @var list<string>
     */
    public const DELIBERATELY_EMPTY = ['calendar', 'agent'];

    private const COUNTS = [
        'brand' => 3, 'budget' => 2, 'plan' => 5, 'mind' => 4, 'file' => 12,
        'link' => 24, 'form' => 6, 'qr' => 9, 'calendar' => 0,
        'campaign' => 7, 'studio' => 11, 'buzz' => 4, 'strategy' => 2,
        'companion' => 3, 'agent' => 0, 'staff' => 4, 'persona' => 5,
        'task' => 38, 'board' => 4,
    ];

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

    private const PEOPLE = [
        ['id' => -1, 'name' => 'A. Rivera', 'changes' => 31],
        ['id' => -2, 'name' => 'N. Haddad', 'changes' => 18],
        ['id' => -3, 'name' => 'S. Tan',    'changes' => 12],
        ['id' => -4, 'name' => 'K. Mbeki',  'changes' => 5],
    ];

    private const WORKED = [
        'link' => [0, 1, 2, 3], 'form' => [0, 2], 'qr' => [1],
        'task' => [0, 1, 2], 'board' => [1], 'campaign' => [0, 3],
        'studio' => [3], 'plan' => [0, 1], 'file' => [2], 'brand' => [3],
        'buzz' => [1], 'staff' => [0], 'persona' => [2],
    ];

    /**
     * What each station holds, so its panel has something to show. A few per
     * kind is enough to read; the station's count is the real total, and the
     * panel says so rather than pretending the list is all of it.
     */
    private const ITEMS = [
        'link'      => ['Autumn blend launch', 'Wholesale enquiry', 'Subscribe — 12 week', 'Cafe finder'],
        'form'      => ['Wholesale enquiry', 'Tasting RSVP', 'Barista application'],
        'qr'        => ['Bag label — Autumn blend', 'Table card — tasting flight', 'Market stall sign'],
        'file'      => ['Roast profile v4.pdf', 'Origin photos', 'Wholesale price list'],
        'plan'      => ['Autumn launch plan', 'Wholesale outreach', 'Tasting events Q4'],
        'mind'      => ['Brand voice', 'Origin stories', 'Wholesale FAQ'],
        'budget'    => ['Launch spend', 'Q4 forecast'],
        'brand'     => ['Northwind core', 'Autumn seasonal', 'Wholesale deck'],
        'campaign'  => ['Autumn blend — email', 'Wholesale — LinkedIn', 'Tasting invites'],
        'studio'    => ['Bag mockups', 'Tasting poster', 'Origin reel'],
        'buzz'      => ['Recent orders', 'Tasting sign-ups'],
        'strategy'  => ['Autumn positioning', 'Wholesale pricing'],
        'companion' => ['Website chat', 'Wholesale enquiries'],
        'staff'     => ['Billing assistant', 'Wholesale replies', 'Tasting scheduler'],
        'persona'   => ['Cafe owner', 'Home subscriber', 'Market shopper'],
        'task'      => ['Approve bag artwork', 'Send wholesale samples', 'Book market stall', 'Photograph the flight'],
        'board'     => ['Launch', 'Wholesale', 'Events'],
    ];

    /**
     * The second line of each item row. It used to fall back to the kind's own
     * label, so every row under "Brand kits" said "Brand kits" beneath a header
     * that already said it -- three identical lines saying nothing. Invented,
     * like everything else here, but invented to carry something.
     *
     * @var array<string, list<string>>
     */
    private const DETAILS = [
        'form'      => ['214 responses', '38 responses', '96 responses'],
        'qr'        => ['612 scans', '144 scans', '301 scans'],
        'file'      => ['2.4 MB · PDF', '18 images', '96 KB · XLSX'],
        'plan'      => ['8 of 14 done', '3 of 9 done', 'Starts 2 Nov'],
        'mind'      => ['Trained 4 days ago', 'Trained last week', 'Draft'],
        'budget'    => ['76% committed', 'Draft'],
        'brand'     => ['6 assets', '4 assets', '11 assets'],
        'campaign'  => ['Sending Tuesday', 'Scheduled', 'Draft'],
        'studio'    => ['12 renders', '3 renders', 'Rendering'],
        'buzz'      => ['Live', 'Live'],
        'strategy'  => ['Approved', 'In review'],
        'companion' => ['148 conversations', '64 conversations'],
        'staff'     => ['Answers billing', 'Answers wholesale', 'Books tastings'],
        'persona'   => ['41% of visitors', '36% of visitors', '23% of visitors'],
        'board'     => ['12 cards', '9 cards', '17 cards'],
    ];

    private const JUNCTIONS = [
        ['from' => 'link', 'to' => 'form', 'count' => 6],
        ['from' => 'link', 'to' => 'qr', 'count' => 4],
        ['from' => 'plan', 'to' => 'link', 'count' => 3],
        ['from' => 'campaign', 'to' => 'studio', 'count' => 5],
        ['from' => 'task', 'to' => 'campaign', 'count' => 2],
    ];

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
                // An empty station measures nothing, exactly as on the real map.
                'figure'  => $count > 0 ? (self::FIGURES[$kind] ?? null) : null,
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

    /**
     * The items a station's panel lists, with the kind's own flavour of
     * detail -- the same three the real panel shows.
     *
     * @return list<array{label:string,detail:string}>
     */
    public static function items(string $kind): array
    {
        $labels = self::ITEMS[$kind] ?? [];
        $out = [];
        foreach ($labels as $i => $label) {
            $out[] = ['label' => $label, 'detail' => match ($kind) {
                'task'  => [0 => 'Done', 1 => '24 Oct', 2 => 'Done', 3 => 'No due date'][$i] ?? 'No due date',
                'link'  => $i === 3 ? 'Inactive' : 'Active',
                default => self::DETAILS[$kind][$i] ?? 'Updated recently',
            }];
        }

        return $out;
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
     * as paragraphs every user read once and scrolled past forever.
     *
     * @return list<array{title:string,body:string}>
     */
    public static function legend(): array
    {
        return [
            [
                'key'   => 'station',
                'title' => 'A station is a kind of thing',
                'body'  => 'Every kind a folder can hold has a station, whether or not this folder uses it. The big number is how many it holds. Open one to see what is inside it.',
            ],
            [
                'key'   => 'dashed',
                'title' => 'A dashed station is empty, not missing',
                'body'  => 'Calendars and Agents are empty here on purpose. An unused station stays on the map, dashed and grey, so you can see what is available rather than only what you already started — and opening it offers to assign something.',
            ],
            [
                'key'   => 'figure',
                'title' => 'The small figure is measured',
                'body'  => 'Under the count, a station may show a figure its stage actually measures — clicks for content, impressions for Buzz, conversations for channels, completion for tasks. A station whose stage measures nothing says "not counted here" rather than showing a zero that would read as a result.',
            ],
            [
                'key'   => 'rails',
                'title' => 'Dashed lines are a suggested order',
                'body'  => 'The coloured dashed rails are one sensible way work moves through a folder: prepare, create, reach, serve the people that brings in, and act on what comes back. They are a suggestion, drawn whether or not you work that way. Nothing on the map claims the work happened in that order.',
            ],
            [
                'key'   => 'junction',
                'title' => 'Solid lines with a number are real',
                'body'  => 'A solid line meeting at a numbered circle is a junction: that many item-to-item connections somebody actually saved between those two kinds, under Resources. Unlike the rails, a junction is a record of something that exists — here, six links tied to forms and five campaigns tied to studio material.',
            ],
            [
                'key'   => 'ai',
                'title' => 'A rounder station runs on AI',
                'body'  => 'Eight of the nineteen kinds are built on AI. They are drawn with rounder corners and a round icon, so you can see at a glance how much of a folder leans on it.',
            ],
            [
                'key'   => 'faces',
                'title' => 'Faces are who changed something',
                'body'  => 'Up to three people who changed anything of that kind in this folder recently, most active first, with the rest as a +n. The workspace owner is left out on purpose — they are in every folder, so counting them says nothing about who else is involved.',
            ],
            [
                'key'   => 'filters',
                'title' => 'The filters narrow the question',
                'body'  => 'On your own map you can hide what is unused, switch whole lines off, separate AI from the rest, or show only what one person has touched. Hidden stations leave their space empty and their rails disappear, so the map never redraws itself into a different shape.',
            ],
        ];
    }
}
