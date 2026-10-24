<?php

namespace App\Modules\User\Controllers\Concerns;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskColumn;
use App\Services\Planner\PlannerResources;
use App\Services\Planner\TaskBoardExport;
use App\Services\Planner\TaskBoardSnapshot;
use App\Services\Planner\TaskWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

trait TaskAdvanced
{
    private function taskCapability(TaskBoard $board, string $action): void
    {
        $this->authorizeView($board);
        abort_unless(auth()->user()->canInWorkspace(app('current_workspace'), 'tasks.'.$action), 403);
    }

    public function completeTask(Request $request, TaskCard $card)
    {
        $this->authorizeEdit($card->board);
        $v = $request->validate(['completed' => 'required|boolean']);
        $card = app(TaskWorkflow::class)->complete($card, $v['completed']);

        return response()->json(['ok' => true, 'card' => $card, 'revision' => TaskBoardSnapshot::cardRevision($card)]);
    }

    public function saveTransitions(Request $request, TaskColumn $column)
    {
        $this->taskCapability($column->board, 'workflow');
        $data = $request->validate(['transitions' => 'present|array|max:20', 'transitions.*.target_column_id' => 'required|integer', 'transitions.*.trigger' => 'required|in:completed,manual', 'transitions.*.condition' => 'required|in:always,priority,label', 'transitions.*.value' => 'nullable|string|max:80']);
        foreach ($data['transitions'] as $rule) {
            abort_unless(TaskColumn::where('board_id', $column->board_id)->where('id', '!=', $column->id)->where('id', $rule['target_column_id'])->exists(), 422, 'Choose a different column on this board.');
            if ($rule['condition'] === 'priority') {
                abort_unless(in_array($rule['value'] ?? '', array_keys(TaskCard::priorities())), 422, 'Choose a valid priority.');
            }if ($rule['condition'] === 'label') {
                abort_unless($column->board->labels()->where('id', $rule['value'] ?? 0)->exists(), 422, 'Choose a category on this board.');
            }
        }
        $column->update($data);

        return response()->json(['ok' => true]);
    }

    public function relatedChoices(Request $request, TaskBoard $board)
    {
        $this->authorizeView($board);
        $v = $request->validate(['kind' => 'required|in:link,mind,staff,file,project,contact,invoice,calendar', 'q' => 'nullable|string|max:100']);
        $kind = $v['kind'];
        $user = auth()->user();
        $ws = app('current_workspace');
        if ($kind === 'project') {
            abort_unless($user->canInWorkspace($ws, 'projects.view'), 403);
            $rows = $this->projectChoices();
            $data = $rows->map(fn ($r) => ['id' => $r->id, 'title' => $r->name, 'type' => 'project', 'url' => route('user.projects.show', $r)]);
        } elseif ($kind === 'invoice') {
            abort_unless($user->canInWorkspace($ws, 'invoices.view'), 403);
            $data = Invoice::where('workspace_id', $ws->id)->where('kind', 'client')->when(! empty($v['q']), fn ($q) => $q->where('number', 'ilike', '%'.$v['q'].'%'))->limit(100)->get(['id', 'number'])->map(fn ($r) => ['id' => $r->id, 'title' => $r->number, 'type' => 'invoice', 'url' => route('user.client-invoices.show', $r)]);
        } elseif ($kind === 'contact') {
            abort_unless($user->canInWorkspace($ws, 'contacts.view'), 403);
            $data = Contact::where('user_id', workspace_owner_id())->when(! empty($v['q']), fn ($q) => $q->where('name', 'ilike', '%'.$v['q'].'%'))->limit(100)->get(['id', 'name'])->map(fn ($r) => ['id' => $r->id, 'title' => $r->name ?? 'Contact', 'type' => 'contact', 'url' => route('user.contacts.show', $r)]);
        } else {
            $resources = app(PlannerResources::class);
            $data = $resources->query($kind, $user)->when(! empty($v['q']), function ($query) use ($v) {
                $table = $query->getModel()->getTable();
                foreach (['title', 'name', 'original_name'] as $field) {
                    if (Schema::hasColumn($table, $field)) {
                        $query->where($field, 'ilike', '%'.$v['q'].'%');
                        break;
                    }
                }
            })->orderByDesc('id')->limit(100)->get()->map(fn ($r) => ['id' => $r->id, 'title' => $resources->label($r), 'type' => $kind === 'staff' ? 'other' : ($kind === 'calendar' ? 'event' : $kind), 'url' => $resources->url($kind, $r)]);
        }
        $q = mb_strtolower($v['q'] ?? '');

        return response()->json(['items' => $data->filter(fn ($r) => ! $q || str_contains(mb_strtolower($r['title']), $q))->take(30)->map(function ($item) {
            $item['title'] = mb_substr($item['title'], 0, 160);

            return $item;
        })->values()]);
    }

    public function exportBoard(Request $request, TaskBoard $board)
    {
        $this->taskCapability($board, 'export');
        $v = $request->validate(['mode' => 'required|in:board,gantt', 'format' => 'required|in:svg,pdf', 'from' => 'nullable|date_format:Y-m-d', 'days' => 'nullable|integer|between:7,93']);
        $service = app(TaskBoardExport::class);
        $from = $v['from'] ?? today()->toDateString();
        $days = $v['days'] ?? 35;
        $format = $v['format'];
        $body = $format === 'pdf' ? $service->pdf($board, $v['mode'], $from, $days) : $service->svg($board, $v['mode'], $from, $days);

        return response($body,200,['Content-Type' => $format === 'pdf' ? 'application/pdf' : 'image/svg+xml', 'Content-Disposition' => 'attachment; filename="task-'.$v['mode'].'.'.$format.'"', 'Cache-Control' => 'private, no-store']);
    }
}
