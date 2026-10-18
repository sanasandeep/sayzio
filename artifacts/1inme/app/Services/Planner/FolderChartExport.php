<?php

namespace App\Services\Planner;

/** Self-contained SVGs: no remote assets, and all labels are XML escaped. */
class FolderChartExport
{
    /**
     * The views that can be exported. Hub is gone, so its export is too -- a
     * picture of a screen nobody can open is one more thing to keep in step
     * with nothing. The folder page loops over this rather than repeating
     * the list, so the two cannot disagree about what exists.
     *
     * @var list<string>
     */
    public const MODES = ['flow', 'people'];

    public static function render(string $mode, string $name, array $resources, array $people, string $color = '#365ce9', iterable $relations = []): string
    {
        $color = preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : '#365ce9';

        // The Flow export draws the network map itself, from the same tables
        // the screen is drawn from. It used to carry its own four-stage list
        // -- Prepare, Create, Reach, Act -- which silently dropped Serve and
        // six kinds with it. See flowNetwork().
        if ($mode === 'flow') {
            return self::flowNetwork($name, $resources, $color, $relations);
        }

        $esc = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        // Only 'people' reaches here: 'flow' returned above, and Hub is gone.
        $labels = array_map(fn ($person) => [$person['name'].' · '.$person['role'], $person['open'].' open · '.$person['completed'].' complete · '.$person['actions'].' recorded actions (30d)'], $people);
        if (! $labels) {
            $labels = [['No people with access', 'Add workspace members to begin']];
        }
        $height = max(450, 200 + count($labels) * 94);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="'.$height.'" viewBox="0 0 1200 '.$height.'"><rect width="1200" height="'.$height.'" fill="#f8faff"/><g font-family="Arial,sans-serif" fill="#17213b"><text x="44" y="54" font-size="28" font-weight="700">'.$esc($name).' · '.$esc(ucfirst($mode)).'</text><text x="44" y="85" font-size="14" fill="#65718b">Visible project records · '.now()->format('d M Y').'</text>';
        foreach ($labels as $index => [$label,$detail]) {
                $x = 44;
                $y = 120 + $index * 94;

            $width = 1110;
            $svg .= '<rect x="'.$x.'" y="'.$y.'" width="'.$width.'" height="78" rx="16" fill="white" stroke="#dbe1ef"/><text x="'.($x + 18).'" y="'.($y + 30).'" font-size="16" font-weight="700">'.$esc(mb_strimwidth($label, 0, 95, '…')).'</text><text x="'.($x + 18).'" y="'.($y + 56).'" font-size="13" fill="#65718b">'.$esc($detail).'</text>';
        }
        $svg .= '<text x="44" y="'.($height - 30).'" font-size="13" fill="#65718b">'.$esc('Current assignments; actions from visible tasks in the last 30 days. Shared tasks count for each assignee.').'</text></g></svg>';

        return $svg;
    }

    // ---- Flow: the network map, drawn from the map's own tables -----------

    /** Card geometry, in the same 8x5 grid the screen lays out. */
    private const CARD_W = 170;
    private const CARD_H = 96;
    private const COL_PITCH = 196;
    private const ROW_PITCH = 134;
    private const ORIGIN_X = 48;
    private const ORIGIN_Y = 132;

    /**
     * Every station, every rail, every junction -- derived from
     * FolderFlowNetwork and FolderFlowStages, never restated here.
     *
     * The previous version of this export kept its own stage list. It had
     * four stages where the map has five, so Serve was missing entirely and
     * companion, agent, staff, persona, calendar and board -- six of the
     * nineteen kinds -- never appeared in an exported picture at all. Anyone
     * who exported the Flow tab got a diagram that disagreed with the screen
     * and said nothing about it. A test now holds every kind to appearing.
     */
    private static function flowNetwork(string $name, array $resources, string $color, iterable $relations): string
    {
        $esc = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $map = FolderFlowNetwork::map($resources, $relations, []);
        $stages = FolderFlowStages::all();
        $labels = PlannerResources::typeLabels();

        $width = self::ORIGIN_X * 2 + (FolderFlowNetwork::COLS - 1) * self::COL_PITCH + self::CARD_W;
        $height = self::ORIGIN_Y + (FolderFlowNetwork::ROWS - 1) * self::ROW_PITCH + self::CARD_H + 108;

        $place = [];
        foreach ($map['stations'] as $station) {
            $x = self::ORIGIN_X + ($station['col'] - 1) * self::COL_PITCH;
            $y = self::ORIGIN_Y + ($station['row'] - 1) * self::ROW_PITCH;
            $place[$station['kind']] = [
                'x' => $x, 'y' => $y,
                'cx' => $x + self::CARD_W / 2, 'cy' => $y + self::CARD_H / 2,
                'station' => $station,
            ];
        }

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'">'
            .'<rect width="'.$width.'" height="'.$height.'" fill="#f8faff"/>'
            .'<g font-family="Arial,sans-serif" fill="#17213b">'
            .'<text x="48" y="54" font-size="28" font-weight="700">'.$esc($name).' · Flow</text>'
            .'<text x="48" y="85" font-size="14" fill="#65718b">Visible project records · '.now()->format('d M Y').'</text>';

        // Rails first, so the opaque cards sit over them exactly as on screen.
        foreach (FolderFlowNetwork::rails() as $rail) {
            $stroke = $stages[$rail['stage']]['color'] ?? '#8397d8';
            $points = $rail['points'];
            for ($i = 0; $i < count($points) - 1; $i++) {
                $from = self::point($points[$i], $place);
                $to = self::point($points[$i + 1], $place);
                if ($from === null || $to === null) {
                    continue;
                }
                $svg .= '<path d="M'.$from[0].' '.$from[1].' L'.$to[0].' '.$to[1].'" fill="none" stroke="'.$stroke
                    .'" stroke-width="3" stroke-linecap="round" stroke-dasharray="9 8" opacity=".5"/>';
            }
        }

        // Junctions: saved item-to-item connections. Real, so they are drawn.
        foreach ($map['edges'] as $edge) {
            if ($edge['from'] === null || $edge['from'] === $edge['to']
                || ! isset($place[$edge['from']], $place[$edge['to']])) {
                continue;
            }
            $a = $place[$edge['from']];
            $b = $place[$edge['to']];
            $mx = ($a['cx'] + $b['cx']) / 2;
            $my = ($a['cy'] + $b['cy']) / 2;
            $svg .= '<path d="M'.$a['cx'].' '.$a['cy'].' L'.$b['cx'].' '.$b['cy'].'" fill="none" stroke="#637cef" stroke-width="'
                .min(2.5 + $edge['count'], 7).'" stroke-linecap="round" opacity=".9"/>'
                .'<circle cx="'.$mx.'" cy="'.$my.'" r="12" fill="white" stroke="#637cef" stroke-width="1.7"/>'
                .'<text x="'.$mx.'" y="'.($my + 4).'" text-anchor="middle" font-size="12" font-weight="700">'.$esc($edge['count']).'</text>';
        }

        foreach ($place as $kind => $spot) {
            $station = $spot['station'];
            $tone = $stages[$station['stage']]['color'] ?? $color;
            $used = $station['in_use'];
            // An AI station is the rounder box, the same distinction the
            // screen draws. Unused is dashed and grey, there but not claimed.
            $radius = $station['ai'] ? 22 : 14;
            $svg .= '<rect x="'.$spot['x'].'" y="'.$spot['y'].'" width="'.self::CARD_W.'" height="'.self::CARD_H
                .'" rx="'.$radius.'" fill="white" stroke="'.($used ? $tone : '#c8d0e2').'" stroke-width="'.($used ? 2 : 1.4).'"'
                .($used ? '' : ' stroke-dasharray="5 4"').'/>';
            if ($station['ai']) {
                $svg .= '<rect x="'.($spot['x'] + 4).'" y="'.($spot['y'] + 4).'" width="'.(self::CARD_W - 8).'" height="'.(self::CARD_H - 8)
                    .'" rx="'.($radius - 4).'" fill="none" stroke="'.($used ? $tone : '#c8d0e2').'" stroke-width="1" opacity=".45"/>';
            }
            $svg .= '<g transform="translate('.($spot['x'] + 14).' '.($spot['y'] + 13).')" fill="none" stroke="'
                .($used ? $tone : '#9aa5bd').'" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">'
                .$station['icon'].'</g>'
                .'<text x="'.($spot['x'] + 46).'" y="'.($spot['y'] + 29).'" font-size="13" font-weight="700" fill="'
                .($used ? '#17213b' : '#78839b').'">'.$esc(mb_strimwidth($labels[$kind] ?? $kind, 0, 18, '…')).'</text>'
                .'<text x="'.($spot['x'] + 14).'" y="'.($spot['y'] + 70).'" font-size="26" font-weight="700" fill="'
                .($used ? '#17213b' : '#9aa5bd').'">'.$esc(number_format($station['count'])).'</text>'
                .'<text x="'.($spot['x'] + 14).'" y="'.($spot['y'] + 87).'" font-size="10.5" fill="#78839b">'
                .$esc($stages[$station['stage']]['eyebrow'] ?? '').($station['ai'] ? ' · runs on AI' : '').'</text>';
        }

        $legendY = $height - 62;
        $x = 48;
        foreach ($stages as $stage) {
            $svg .= '<rect x="'.$x.'" y="'.($legendY - 8).'" width="22" height="4" rx="2" fill="'.$stage['color'].'"/>'
                .'<text x="'.($x + 30).'" y="'.($legendY - 1).'" font-size="12.5" fill="#65718b">'.$esc($stage['eyebrow']).'</text>';
            $x += 34 + mb_strlen($stage['eyebrow']) * 8;
        }

        $svg .= '<text x="48" y="'.($height - 28).'" font-size="12.5" fill="#65718b">'
            .'Every resource kind this folder can hold is shown, used or not. Rails are a suggested order, not measured attribution; '
            .'a numbered junction is a count of saved item-to-item connections. Counts reflect what the exporting user can see.'
            .'</text></g></svg>';

        return $svg;
    }

    /** A rail point: a station centre, or a corner taking x from one and y from another. */
    private static function point($spec, array $place): ?array
    {
        if (is_string($spec)) {
            return isset($place[$spec]) ? [$place[$spec]['cx'], $place[$spec]['cy']] : null;
        }
        if (! isset($place[$spec['x']], $place[$spec['y']])) {
            return null;
        }

        return [$place[$spec['x']]['cx'], $place[$spec['y']]['cy']];
    }
}
