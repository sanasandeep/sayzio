<?php

namespace App\Services\Planner;

/** Self-contained SVGs: no remote assets, and all labels are XML escaped. */
class FolderChartExport
{
    public static function render(string $mode, string $name, array $resources, array $people): string
    {
        $esc = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $items = collect($resources);
        $labels = match ($mode) {
            'hub' => collect(['Links' => ['link'], 'AI Minds' => ['mind'], 'Personas' => ['persona'], 'Agents & Staff' => ['agent', 'staff', 'companion'], 'Planner plans' => ['plan'], 'Tasks' => ['task'], 'Files, kits & budgets' => ['form', 'qr', 'file', 'calendar', 'board', 'brand', 'budget'], 'Campaigns' => ['campaign', 'strategy', 'buzz', 'studio']])->map(fn ($kinds, $label) => [$label, $items->whereIn('kind', $kinds)->count().' assigned'])->values()->all(),
            'flow' => collect(['Prepare' => ['file', 'mind', 'plan', 'brand', 'budget'], 'Create' => ['link', 'form', 'qr'], 'Reach' => ['campaign', 'studio', 'buzz', 'strategy'], 'Act' => ['task']])->map(fn ($kinds, $label) => [$label, $items->whereIn('kind', $kinds)->count().' assigned items'])->values()->all(),
            default => array_map(fn ($person) => [$person['name'].' · '.$person['role'], $person['open'].' open · '.$person['completed'].' complete · '.$person['actions'].' recorded actions (30d)'], $people),
        };
        if (! $labels) {
            $labels = [['No people with access', 'Add workspace members to begin']];
        }
        $height = $mode === 'people' ? max(450, 200 + count($labels) * 94) : 630;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="'.$height.'" viewBox="0 0 1200 '.$height.'"><rect width="1200" height="'.$height.'" fill="#f8faff"/><g font-family="Arial,sans-serif" fill="#17213b"><text x="44" y="54" font-size="28" font-weight="700">'.$esc($name).' · '.$esc(ucfirst($mode)).'</text><text x="44" y="85" font-size="14" fill="#65718b">Visible project records · '.now()->format('d M Y').'</text>';
        foreach ($labels as $index => [$label,$detail]) {
            if ($mode === 'hub') {
                $x = 44 + ($index % 4) * 283;
                $y = $index < 4 ? 130 : 430;
                $svg .= '<path d="M600 310 L'.($x + 130).' '.($index < 4 ? $y + 90 : $y).'" stroke="#bbc8ff" stroke-width="2"/>';
            } elseif ($mode === 'flow') {
                $x = 44 + $index * 283;
                $y = 230;
                if ($index < 3) {
                    $svg .= '<path d="M'.($x + 260).' 275 H'.($x + 283).'" stroke="#8397d8" stroke-width="2" stroke-dasharray="5 4"/>';
                }
            } else {
                $x = 44;
                $y = 120 + $index * 94;
            }
            $width = $mode === 'people' ? 1110 : 260;
            $svg .= '<rect x="'.$x.'" y="'.$y.'" width="'.$width.'" height="78" rx="16" fill="white" stroke="#dbe1ef"/><text x="'.($x + 18).'" y="'.($y + 30).'" font-size="16" font-weight="700">'.$esc(mb_strimwidth($label, 0, $mode === 'people' ? 95 : 25, '…')).'</text><text x="'.($x + 18).'" y="'.($y + 56).'" font-size="13" fill="#65718b">'.$esc($detail).'</text>';
        }
        if ($mode === 'hub') {
            $svg .= '<rect x="490" y="270" width="220" height="80" rx="20" fill="#eaf0ff" stroke="#afbfff"/><text x="600" y="315" text-anchor="middle" font-size="18" font-weight="700">Project hub</text>';
        }
        $svg .= '<text x="44" y="'.($height - 30).'" font-size="13" fill="#65718b">'.$esc($mode === 'flow' ? 'Suggested workflow; not measured attribution.' : ($mode === 'people' ? 'Current assignments; actions from visible tasks in the last 30 days. Shared tasks count for each assignee.' : 'Counts reflect the resources visible to the exporting user.')).'</text></g></svg>';

        return $svg;
    }
}
