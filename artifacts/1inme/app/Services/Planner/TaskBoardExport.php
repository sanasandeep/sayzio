<?php

namespace App\Services\Planner;

use App\Modules\User\Models\TaskBoard;
use Carbon\CarbonImmutable;
use Dompdf\Dompdf;

class TaskBoardExport
{
    private function text(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public function svg(TaskBoard $board, string $mode, string $from, int $days = 35, ?array $only = null): string
    {
        $board->loadMissing(['columns.cards' => fn ($q) => $q->whereNull('archived_at')->orderBy('position')]);
        $columns = $board->columns->sortBy('position');
        $tasks = $columns->flatMap->cards;
        if ($only !== null) {
            $tasks = $tasks->whereIn('id', $only);
        }
        abort_if($tasks->count() > 500 || $columns->count() > 20, 422, 'Export up to 500 tasks and 20 columns at a time.');
        $width = $mode === 'gantt' ? 1400 : max(700, $columns->count() * 300 + 40);
        $height = $mode === 'gantt' ? max(260, 150 + $tasks->count() * 54) : max(260, 150 + ($columns->map(fn ($c) => $only === null ? $c->cards->count() : $c->cards->whereIn('id', $only)->count())->max() ?? 0) * 100);
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'"><rect width="100%" height="100%" fill="#f8fafc"/><text x="24" y="36" font-size="24" font-family="sans-serif" fill="#172033">'.$this->text($board->name).'</text><text x="24" y="63" font-size="13" font-family="sans-serif" fill="#64748b">'.($mode === 'gantt' ? 'Timeline from '.$from.' · '.$days.' days' : 'Board snapshot').' · '.now()->format('Y-m-d H:i').' UTC</text>';
        if ($mode === 'board') {
            foreach ($columns->values() as $i => $col) {
                $x = 20 + $i * 300;
                $svg .= '<rect x="'.$x.'" y="90" width="282" height="'.($height - 110).'" rx="12" fill="#eef2f7"/><text x="'.($x + 12).'" y="117" font-size="16" font-family="sans-serif" fill="#172033">'.$this->text(mb_substr($col->name, 0, 28)).'</text>';
                foreach (($only === null ? $col->cards : $col->cards->whereIn('id', $only))->values() as $j => $task) {
                    $y = 135 + $j * 100;
                    $svg .= '<rect x="'.($x + 10).'" y="'.$y.'" width="262" height="88" rx="8" fill="white" stroke="#dce3ee"/><text x="'.($x + 20).'" y="'.($y + 23).'" font-size="14" font-family="sans-serif" fill="#172033">'.$this->text(mb_substr($task->title, 0, 31)).'</text><text x="'.($x + 20).'" y="'.($y + 46).'" font-size="12" font-family="sans-serif" fill="#64748b">'.$this->text($task->priority.' · '.($task->due_date?->format('M j') ?? 'No due date')).'</text><rect x="'.($x + 20).'" y="'.($y + 62).'" width="230" height="6" rx="3" fill="#e2e8f0"/><rect x="'.($x + 20).'" y="'.($y + 62).'" width="'.(230 * $task->progress / 100).'" height="6" rx="3" fill="#536dfe"/>';
                }
            }
        } else {
            $start = CarbonImmutable::parse($from);
            $cell = 1040 / $days;
            for ($d = 0; $d < $days; $d++) {
                $x = 340 + $d * $cell;
                $date = $start->addDays($d);
                $svg .= '<rect x="'.$x.'" y="85" width="'.$cell.'" height="'.($height - 105).'" fill="'.($date->isWeekend() ? '#edf1f7' : '#ffffff').'"/><text x="'.($x + 3).'" y="105" font-size="10" font-family="sans-serif" fill="#64748b">'.$date->format('m/d').'</text>';
            }
            foreach ($tasks->values() as $i => $task) {
                $y = 125 + $i * 54;
                $svg .= '<text x="24" y="'.($y + 21).'" font-size="14" font-family="sans-serif" fill="#172033">'.$this->text(mb_substr($task->title, 0, 34)).'</text><text x="24" y="'.($y + 39).'" font-size="11" font-family="sans-serif" fill="#64748b">'.$task->progress.'% · '.$this->text($task->column->name).'</text>';
                if (! $task->start_date && ! $task->due_date) {
                    $svg .= '<text x="350" y="'.($y + 23).'" font-size="12" font-family="sans-serif" fill="#64748b">Unscheduled</text>';

                    continue;
                }$a = CarbonImmutable::parse($task->start_date ?? $task->due_date);
                $b = CarbonImmutable::parse($task->due_date ?? $task->start_date);
                $left = (int) $start->diffInDays($a, false);
                $right = (int) $start->diffInDays($b, false);
                if ($right < 0 || $left >= $days) {
                    continue;
                }$x = 340 + max(0, $left) * $cell;
                $w = (min($days - 1, $right) - max(0, $left) + 1) * $cell - 3;
                $svg .= '<rect x="'.$x.'" y="'.($y + 8).'" width="'.$w.'" height="25" rx="6" fill="#e1e8ff"/><rect x="'.$x.'" y="'.($y + 8).'" width="'.($w * $task->progress / 100).'" height="25" rx="6" fill="'.($task->completed_at ? '#15947c' : '#536dfe').'"/>';
            }
        }

        return $svg.'</svg>';
    }

    public function pdf(TaskBoard $board, string $mode, string $from, int $days): string
    {
        $board->load(['columns.cards' => fn ($q) => $q->whereNull('archived_at')->orderBy('position')]);
        $ids = $board->columns->flatMap->cards->pluck('id')->all();
        abort_if(count($ids) > 500, 422, 'Export up to 500 tasks.');
        $pages = [];
        if ($mode === 'gantt') {
            foreach (array_chunk($ids ?: [0], 12) as $chunk) {
                $pages[] = $this->svg($board, $mode, $from, $days, $chunk);
            }
        } else {
            foreach ($board->columns->sortBy('position')->chunk(3) as $group) {
                $copy = clone $board;
                $copy->setRelation('columns', $group);
                $max = max(1, $group->map(fn ($c) => $c->cards->count())->max());
                for ($offset = 0; $offset < $max; $offset += 8) {
                    $chunk = $group->flatMap(fn ($c) => $c->cards->slice($offset, 8))->pluck('id')->all();
                    $pages[] = $this->svg($copy, $mode, $from, $days, $chunk);
                }
            }
        }
        $html = '<html><head><style>@page{margin:20px}body{font-family:DejaVu Sans}img{width:100%;height:auto}.page{page-break-after:always}.page:last-child{page-break-after:auto}</style></head><body>';
        foreach ($pages as $i => $svg) {
            $html .= '<div class="page"><img src="data:image/svg+xml;base64,'.base64_encode($svg).'"/><p style="font-size:10px;color:#64748b">Page '.($i + 1).' of '.count($pages).'</p></div>';
        }
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false]);
        $pdf->loadHtml($html.'</body></html>');
        $pdf->setPaper('a3',$mode === 'board' ? 'portrait' : 'landscape');
        $pdf->render();

        return $pdf->output();
    }
}
