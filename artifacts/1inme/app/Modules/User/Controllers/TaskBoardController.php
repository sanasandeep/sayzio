<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\TaskActivity;
use App\Modules\User\Models\TaskAttachment;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskColumn;
use App\Modules\User\Models\TaskComment;
use App\Modules\User\Models\TaskLabel;
use App\Modules\User\Models\TaskSubtask;
use App\Modules\User\Models\TaskTimeEntry;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\WorkspaceMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaskBoardController extends Controller
{
    /** Default columns seeded into every new board (per the v1 task spec). */
    private const STARTER_COLUMNS = [
        ['name' => 'Todo',  'color' => '#64748b', 'is_done' => false],
        ['name' => 'Doing', 'color' => '#3b82f6', 'is_done' => false],
        ['name' => 'Done',  'color' => '#10b981', 'is_done' => true ],
    ];

    /** Boards listing — separates personal and team boards for the current user. */
    public function index()
    {
        $userId = auth()->id();

        $personal = TaskBoard::query()
            ->where('scope', 'personal')
            ->where('owner_user_id', $userId)
            ->whereNull('archived_at')
            ->orderBy('position')->orderBy('id')
            ->withCount(['cards as open_cards_count' => function ($q) {
                $q->whereNull('completed_at')->whereNull('archived_at');
            }])
            ->get();

        $team = TaskBoard::query()
            ->where('scope', 'team')
            ->whereNull('archived_at')
            ->orderBy('position')->orderBy('id')
            ->withCount(['cards as open_cards_count' => function ($q) {
                $q->whereNull('completed_at')->whereNull('archived_at');
            }])
            ->get();

        // Auto-seed a personal board only inside the user's own personal workspace.
        $ws = app('current_workspace');
        if ($personal->isEmpty() && $ws && $ws->is_personal && (int) $ws->owner_user_id === (int) $userId) {
            $personal = collect([$this->createBoard('My Tasks', 'personal', '#8b5cf6')])
                ->each->loadCount(['cards as open_cards_count' => function ($q) {
                    $q->whereNull('completed_at')->whereNull('archived_at');
                }]);
        }

        // Archived boards: shown in the "Archived" panel for restore / hard-delete.
        $archived = TaskBoard::query()
            ->whereNotNull('archived_at')
            ->where(function ($q) use ($userId) {
                $q->where(function ($q2) use ($userId) {
                    $q2->where('scope', 'personal')->where('owner_user_id', $userId);
                })->orWhere('scope', 'team');
            })
            ->orderByDesc('archived_at')
            ->get();

        return view('user.tasks.index', compact('personal', 'team', 'archived'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:120',
            'scope' => 'nullable|in:team,personal',
            'color' => 'nullable|string|max:16',
        ]);
        $scope = $data['scope'] ?? 'team';
        $ws    = app('current_workspace');

        if ($scope === 'personal') {
            // Personal boards live in the user's personal workspace only.
            if (!$ws || !$ws->is_personal || (int) $ws->owner_user_id !== (int) auth()->id()) {
                abort(422, 'Personal boards can only be created in your personal workspace.');
            }
        } elseif (!auth()->user()->canInWorkspace($ws, 'tasks.create')) {
            abort(403);
        }

        $board = $this->createBoard($data['name'], $scope, $data['color'] ?? null);

        return redirect()
            ->route('user.tasks.show', $board)
            ->with('success', $scope === 'personal' ? 'Personal board created.' : 'Team board created.');
    }

    public function show(TaskBoard $board)
    {
        $this->authorizeView($board);

        $board->load([
            'columns' => fn ($q) => $q->orderBy('position'),
            'columns.cards' => fn ($q) => $q->whereNull('archived_at')->orderBy('position'),
            'columns.cards.assignees:id,name,avatar',
            'columns.cards.labels:id,name,color',
            'columns.cards.subtasks',
            'labels',
        ]);

        $members = $this->workspaceMembers();

        return view('user.tasks.show', [
            'board'      => $board,
            'members'    => $members,
            'priorities' => TaskCard::priorities(),
        ]);
    }

    /** Allow-list HTML sanitizer: strips unknown tags/attrs and non-(http|https|mailto) hrefs. */
    private function sanitizeHtml(?string $html): ?string
    {
        if ($html === null) return null;
        $html = trim($html);
        if ($html === '') return '';

        $allowedTags = [
            'p','br','b','strong','i','em','u','s','strike','ul','ol','li',
            'a','blockquote','code','pre','h1','h2','h3','span','div',
        ];
        $allowedAttrs = [
            'a' => ['href', 'title', 'rel', 'target'],
        ];

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8"?><div id="__rt_root">' . $html . '</div>';
        if (!$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET)) {
            libxml_clear_errors();
            return '';
        }
        libxml_clear_errors();

        $root = $doc->getElementById('__rt_root');
        if (!$root) return '';

        $walk = function (\DOMNode $node) use (&$walk, $allowedTags, $allowedAttrs, $root) {
            $children = iterator_to_array($node->childNodes);
            foreach ($children as $child) {
                if ($child instanceof \DOMElement) {
                    $tag = strtolower($child->tagName);
                    if (!in_array($tag, $allowedTags, true)) {
                        $text = $node->ownerDocument->createTextNode($child->textContent);
                        $node->replaceChild($text, $child);
                        continue;
                    }
                    $allowedForTag = $allowedAttrs[$tag] ?? [];
                    foreach (iterator_to_array($child->attributes) as $attr) {
                        $name = strtolower($attr->name);
                        if (!in_array($name, $allowedForTag, true)) {
                            $child->removeAttribute($attr->name);
                            continue;
                        }
                        if ($name === 'href') {
                            $val = trim($attr->value);
                            $decoded = html_entity_decode($val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                            $stripped = preg_replace('/\s+/', '', $decoded);
                            if (!preg_match('#^(https?:|mailto:|/|\#)#i', $stripped)) {
                                $child->removeAttribute('href');
                                continue;
                            }
                        }
                        if ($name === 'target') {
                            $child->setAttribute('target', '_blank');
                            $child->setAttribute('rel', 'noopener noreferrer nofollow');
                        }
                    }
                    $walk($child);
                } elseif ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                    $node->removeChild($child);
                }
            }
        };
        $walk($root);

        $out = '';
        foreach ($root->childNodes as $c) {
            $out .= $doc->saveHTML($c);
        }
        return $out;
    }

    public function updateBoard(Request $request, TaskBoard $board)
    {
        $this->authorizeEdit($board);
        $data = $request->validate([
            'name'        => 'required|string|max:120',
            'description' => 'nullable|string|max:2000',
            'color'       => 'nullable|string|max:16',
        ]);
        $board->update($data);
        return back()->with('success', 'Board updated.');
    }

    /** Soft-archive a board (sets archived_at). Editors and above only. */
    public function archiveBoard(TaskBoard $board)
    {
        $this->authorizeDelete($board);
        if (!$board->archived_at) {
            $board->update(['archived_at' => now()]);
        }
        return back()->with('success', 'Board archived.');
    }

    /** Restore a soft-archived board so it shows up in the index again. */
    public function unarchiveBoard(TaskBoard $board)
    {
        $this->authorizeDelete($board);
        if ($board->archived_at) {
            $board->update(['archived_at' => null]);
        }
        return back()->with('success', 'Board restored.');
    }

    public function destroyBoard(TaskBoard $board)
    {
        $this->authorizeDelete($board);
        DB::transaction(function () use ($board) {
            $cardIds = $board->cards()->pluck('id');
            $this->purgeCardAttachments($cardIds);
            DB::table('task_card_assignees')->whereIn('card_id', $cardIds)->delete();
            DB::table('task_card_labels')->whereIn('card_id', $cardIds)->delete();
            DB::table('task_subtasks')->whereIn('card_id', $cardIds)->delete();
            DB::table('task_comments')->whereIn('card_id', $cardIds)->delete();
            DB::table('task_activities')->whereIn('card_id', $cardIds)->delete();
            $board->cards()->delete();
            $board->labels()->delete();
            $board->columns()->delete();
            $board->delete();
        });
        return redirect()->route('user.tasks.index')->with('success', 'Board deleted.');
    }

    /** Delete attachment rows and their underlying files for the given cards. */
    private function purgeCardAttachments($cardIds): void
    {
        $atts = TaskAttachment::whereIn('card_id', $cardIds)->get(['id', 'disk', 'path']);
        foreach ($atts as $att) {
            \Storage::disk($att->disk)->delete($att->path);
        }
        TaskAttachment::whereIn('card_id', $cardIds)->delete();
    }

    // ----- Columns ----------------------------------------------------------

    public function storeColumn(Request $request, TaskBoard $board)
    {
        $this->authorizeEdit($board);
        $data = $request->validate([
            'name'      => 'required|string|max:80',
            'color'     => 'nullable|string|max:16',
            'wip_limit' => 'nullable|integer|min:1|max:999',
            'is_done'   => 'nullable|boolean',
        ]);
        $position = (int) ($board->columns()->max('position') ?? 0) + 1;
        $column = $board->columns()->create([
            'workspace_id' => $board->workspace_id,
            'name'         => $data['name'],
            'color'        => $data['color'] ?? null,
            'wip_limit'    => $data['wip_limit'] ?? null,
            'is_done'      => (bool) ($data['is_done'] ?? false),
            'position'     => $position,
        ]);
        return back()->with('success', "Added “{$column->name}” column.");
    }

    public function updateColumn(Request $request, TaskColumn $column)
    {
        $board = $column->board;
        $this->authorizeEdit($board);
        $data = $request->validate([
            'name'      => 'required|string|max:80',
            'color'     => 'nullable|string|max:16',
            'wip_limit' => 'nullable|integer|min:1|max:999',
            'is_done'   => 'nullable|boolean',
        ]);
        $column->update([
            'name'      => $data['name'],
            'color'     => $data['color'] ?? null,
            'wip_limit' => $data['wip_limit'] ?? null,
            'is_done'   => (bool) ($data['is_done'] ?? false),
        ]);
        return back()->with('success', 'Column updated.');
    }

    public function destroyColumn(TaskColumn $column)
    {
        $board = $column->board;
        $this->authorizeEdit($board);
        // Move cards to the first remaining column; if none, archive instead of delete.
        $fallback = $board->columns()->where('id', '!=', $column->id)->orderBy('position')->first();
        if ($fallback) {
            $start = (int) ($fallback->cards()->max('position') ?? 0) + 1;
            foreach ($column->cards()->orderBy('position')->get() as $i => $card) {
                $card->update(['column_id' => $fallback->id, 'position' => $start + $i]);
            }
        } else {
            $column->cards()->update(['archived_at' => now()]);
        }
        $column->delete();
        return back()->with('success', 'Column removed.');
    }

    public function reorderColumns(Request $request, TaskBoard $board)
    {
        $this->authorizeEdit($board);
        $ids = (array) $request->input('order', []);
        DB::transaction(function () use ($board, $ids) {
            foreach ($ids as $i => $id) {
                $board->columns()->where('id', (int) $id)->update(['position' => $i + 1]);
            }
        });
        return response()->json(['ok' => true]);
    }

    // ----- Cards ------------------------------------------------------------

    public function storeCard(Request $request, TaskBoard $board)
    {
        $this->authorizeCreate($board);
        $data = $request->validate([
            'column_id' => 'required|integer|exists:task_columns,id',
            'title'     => 'required|string|max:200',
        ]);
        $column = $board->columns()->where('id', $data['column_id'])->firstOrFail();
        $position = (int) ($column->cards()->max('position') ?? 0) + 1;
        $card = $board->cards()->create([
            'workspace_id' => $board->workspace_id,
            'column_id'    => $column->id,
            'title'        => $data['title'],
            'position'     => $position,
            'priority'     => 'normal',
        ]);
        TaskActivity::log($card->id, auth()->id(), 'created', ['title' => $card->title]);
        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'id' => $card->id]);
        }
        return back();
    }

    public function showCard(TaskCard $card)
    {
        $this->authorizeView($card->board);
        $card->load(['assignees:id,name,avatar', 'labels', 'subtasks', 'comments.user:id,name,avatar', 'activities.user:id,name,avatar', 'column', 'attachments.uploader:id,name', 'cloudAttachments.cloudFile', 'timeEntries.user:id,name']);
        $cardArr = $card->toArray();
        $cardArr['time_entries'] = $card->timeEntries->map(fn ($t) => [
            'id'         => $t->id,
            'minutes'    => (int) $t->minutes,
            'note'       => $t->note,
            'source'     => $t->source,
            'started_at' => optional($t->started_at)->toIso8601String(),
            'ended_at'   => optional($t->ended_at)->toIso8601String(),
            'invoiced'   => (bool) $t->client_invoice_id,
            'user'       => $t->user ? ['id' => $t->user->id, 'name' => $t->user->name] : null,
        ])->all();
        $cardArr['unbilled_minutes'] = $card->unbilledMinutes();
        $cardArr['running_timer']    = optional($card->runningTimer())->only(['id', 'started_at']);
        $cardArr['attachments'] = $card->attachments->map(fn ($a) => array_merge($a->toArray(), [
            'url' => $a->url(),
            'human_size' => $a->humanSize(),
        ]))->all();
        $cardArr['cloud_attachments'] = $card->cloudAttachments->map(fn ($a) => \App\Modules\User\Controllers\CloudFileAttachmentController::serialize($a))->all();
        return response()->json([
            'card'    => $cardArr,
            'members' => $this->workspaceMembers(),
            'labels'  => $card->board->labels,
            'priorities' => TaskCard::priorities(),
        ]);
    }

    public function updateCard(Request $request, TaskCard $card)
    {
        $this->authorizeEdit($card->board);
        $data = $request->validate([
            'title'            => 'sometimes|string|max:200',
            'description'      => 'sometimes|nullable|string|max:8000',
            'description_html' => 'sometimes|nullable|string|max:20000',
            'due_date'         => 'sometimes|nullable|date',
            'priority'         => 'sometimes|in:low,normal,high,urgent',
            'progress'         => 'sometimes|integer|min:0|max:100',
            'billable'         => 'sometimes|boolean',
            'rate_type'        => 'sometimes|nullable|in:hourly,flat',
            'rate_amount_minor'=> 'sometimes|nullable|integer|min:0',
        ]);
        if (array_key_exists('description_html', $data)) {
            $data['description_html'] = $this->sanitizeHtml($data['description_html']);
        }

        $changes = [];
        foreach ($data as $field => $value) {
            if ((string) $card->{$field} !== (string) $value) {
                $changes[$field] = ['from' => $card->{$field}, 'to' => $value];
            }
        }
        $card->update($data);

        foreach ($changes as $field => $delta) {
            $type = match ($field) {
                'title'    => 'renamed',
                'priority' => 'priority',
                'due_date' => 'due_set',
                default    => 'edited',
            };
            TaskActivity::log($card->id, auth()->id(), $type, $delta);
        }

        return response()->json(['ok' => true, 'card' => $card->fresh()]);
    }

    public function moveCard(Request $request, TaskCard $card)
    {
        $this->authorizeEdit($card->board);
        $data = $request->validate([
            'column_id' => 'required|integer|exists:task_columns,id',
            'position'  => 'required|integer|min:0',
        ]);

        $targetColumn = TaskColumn::where('board_id', $card->board_id)->findOrFail($data['column_id']);
        $fromColumnId = $card->column_id;

        DB::transaction(function () use ($card, $targetColumn, $data, $fromColumnId) {
            if ($fromColumnId === $targetColumn->id) {
                $siblings = TaskCard::where('column_id', $targetColumn->id)
                    ->where('id', '!=', $card->id)
                    ->orderBy('position')->get();
            } else {
                $siblings = TaskCard::where('column_id', $targetColumn->id)
                    ->orderBy('position')->get();
            }

            $newOrder = $siblings->values()->all();
            $insertAt = max(0, min((int) $data['position'], count($newOrder)));
            array_splice($newOrder, $insertAt, 0, [$card]);

            foreach ($newOrder as $i => $c) {
                TaskCard::where('id', $c->id)->update([
                    'column_id' => $targetColumn->id,
                    'position'  => $i + 1,
                ]);
            }

            $card->refresh();
            if ($targetColumn->is_done && !$card->completed_at) {
                $card->update(['completed_at' => now()]);
                TaskActivity::log($card->id, auth()->id(), 'completed', []);
            } elseif (!$targetColumn->is_done && $card->completed_at) {
                $card->update(['completed_at' => null]);
                TaskActivity::log($card->id, auth()->id(), 'reopened', []);
            }
        });

        if ($fromColumnId !== $targetColumn->id) {
            TaskActivity::log($card->id, auth()->id(), 'moved', [
                'from' => $fromColumnId,
                'to'   => $targetColumn->id,
                'to_name' => $targetColumn->name,
            ]);
            // Mirror the move back to the linked public roadmap item, if any.
            if ($card->roadmap_item_id) {
                app(\App\Modules\Common\Services\RoadmapKanbanSync::class)
                    ->pullStatusFromCard($card->fresh());
            }
        }

        return response()->json(['ok' => true]);
    }

    public function destroyCard(TaskCard $card)
    {
        $this->authorizeDelete($card->board);
        DB::transaction(function () use ($card) {
            $this->purgeCardAttachments([$card->id]);
            DB::table('task_card_assignees')->where('card_id', $card->id)->delete();
            DB::table('task_card_labels')->where('card_id', $card->id)->delete();
            DB::table('task_subtasks')->where('card_id', $card->id)->delete();
            DB::table('task_comments')->where('card_id', $card->id)->delete();
            DB::table('task_activities')->where('card_id', $card->id)->delete();
            $card->delete();
        });
        return response()->json(['ok' => true]);
    }

    public function assign(Request $request, TaskCard $card)
    {
        $this->authorizeEdit($card->board);
        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);
        $userId = (int) $data['user_id'];

        // Team boards: assignee must belong to the workspace.
        if ($card->board->scope === 'team' && !$this->isWorkspaceMember($userId, $card->workspace_id)) {
            return response()->json(['ok' => false, 'error' => 'User is not a member of this workspace.'], 422);
        }
        // Personal boards: only the owner is assignable.
        if ($card->board->scope === 'personal' && $userId !== (int) $card->board->owner_user_id) {
            return response()->json(['ok' => false, 'error' => 'Personal boards only support the owner as assignee.'], 422);
        }

        if (!$card->assignees()->where('user_id', $userId)->exists()) {
            $card->assignees()->attach($userId);
            TaskActivity::log($card->id, auth()->id(), 'assigned', ['user_id' => $userId]);
            $this->notifyAssignment($card, $userId);
        }
        return response()->json(['ok' => true]);
    }

    public function unassign(TaskCard $card, User $user)
    {
        $this->authorizeEdit($card->board);
        if ($card->assignees()->where('user_id', $user->id)->exists()) {
            $card->assignees()->detach($user->id);
            TaskActivity::log($card->id, auth()->id(), 'unassigned', ['user_id' => $user->id]);
        }
        return response()->json(['ok' => true]);
    }

    // ----- Subtasks ---------------------------------------------------------

    public function storeSubtask(Request $request, TaskCard $card)
    {
        $this->authorizeEdit($card->board);
        $data = $request->validate(['title' => 'required|string|max:240']);
        $position = (int) ($card->subtasks()->max('position') ?? 0) + 1;
        $sub = $card->subtasks()->create([
            'title'    => $data['title'],
            'position' => $position,
        ]);
        return response()->json(['ok' => true, 'subtask' => $sub]);
    }

    public function toggleSubtask(TaskSubtask $subtask)
    {
        $card = $this->resolveScopedCard($subtask->card_id);
        $this->authorizeEdit($card->board);
        $subtask->update(['completed' => !$subtask->completed]);
        return response()->json(['ok' => true, 'completed' => $subtask->completed]);
    }

    public function reorderSubtasks(Request $request, TaskCard $card)
    {
        $card = $this->resolveScopedCard($card->id);
        if (!$card) abort(404);
        $this->authorizeEdit($card->board);

        $ids = $request->input('order', []);
        if (!is_array($ids)) abort(422, 'order must be an array');

        $owned = $card->subtasks()->pluck('id')->all();
        $clean = array_values(array_intersect(array_map('intval', $ids), $owned));
        if (!$clean) return response()->json(['ok' => true, 'updated' => 0]);

        DB::transaction(function () use ($clean, $card) {
            foreach ($clean as $i => $id) {
                DB::table('task_subtasks')
                    ->where('id', $id)
                    ->where('card_id', $card->id)
                    ->update(['position' => $i + 1, 'updated_at' => now()]);
            }
        });
        return response()->json(['ok' => true, 'updated' => count($clean)]);
    }

    public function destroySubtask(TaskSubtask $subtask)
    {
        $card = $this->resolveScopedCard($subtask->card_id);
        $this->authorizeEdit($card->board);
        $subtask->delete();
        return response()->json(['ok' => true]);
    }

    /** Resolve a card honouring the workspace global scope (404 instead of 500 on cross-ws). */
    private function resolveScopedCard(int $cardId): TaskCard
    {
        $card = TaskCard::query()->find($cardId);
        if (!$card) abort(404);
        return $card;
    }

    // ----- Time entries (billing) ------------------------------------------

    /** Start a timer on a card. If one is already running, it's returned as-is. */
    public function startTimer(TaskCard $card)
    {
        $this->authorizeEdit($card->board);
        if (!$card->billable) abort(422, 'Card is not billable.');

        $running = $card->runningTimer();
        if ($running) {
            return response()->json(['ok' => true, 'entry' => $running]);
        }
        $entry = TaskTimeEntry::create([
            'card_id'    => $card->id,
            'user_id'    => auth()->id(),
            'started_at' => now(),
            'source'     => 'timer',
        ]);
        return response()->json(['ok' => true, 'entry' => $entry]);
    }

    /** Stop the running timer on a card and write minutes (rounded up). */
    public function stopTimer(TaskCard $card)
    {
        $this->authorizeEdit($card->board);
        $running = $card->runningTimer();
        if (!$running) return response()->json(['ok' => true, 'entry' => null]);

        $now = now();
        $minutes = max(1, (int) ceil($running->started_at->diffInSeconds($now) / 60));
        $running->update(['ended_at' => $now, 'minutes' => $minutes]);
        return response()->json(['ok' => true, 'entry' => $running->fresh()]);
    }

    /** Manually log minutes against a card (no live timer). */
    public function storeTimeEntry(Request $request, TaskCard $card)
    {
        $this->authorizeEdit($card->board);
        if (!$card->billable) abort(422, 'Card is not billable.');
        $data = $request->validate([
            'minutes' => 'required|integer|min:1|max:1440',
            'note'    => 'nullable|string|max:240',
            'started_at' => 'nullable|date',
        ]);
        $started = isset($data['started_at']) ? \Carbon\Carbon::parse($data['started_at']) : now();
        $entry = TaskTimeEntry::create([
            'card_id'    => $card->id,
            'user_id'    => auth()->id(),
            'started_at' => $started,
            'ended_at'   => $started->copy()->addMinutes((int) $data['minutes']),
            'minutes'    => (int) $data['minutes'],
            'note'       => $data['note'] ?? null,
            'source'     => 'manual',
        ]);
        return response()->json(['ok' => true, 'entry' => $entry]);
    }

    public function destroyTimeEntry(TaskTimeEntry $entry)
    {
        $card = $this->resolveScopedCard($entry->card_id);
        $this->authorizeEdit($card->board);
        if ($entry->client_invoice_id) {
            abort(422, 'Time entry already on an invoice.');
        }
        $entry->delete();
        return response()->json(['ok' => true]);
    }

    /** Set the column where paid cards auto-move to ("Done & Billed"). */
    public function setBilledColumn(Request $request, TaskBoard $board)
    {
        $this->authorizeEdit($board);
        $data = $request->validate([
            'column_id' => 'nullable|integer|exists:task_columns,id',
        ]);
        if (!empty($data['column_id'])) {
            // Must belong to this board.
            TaskColumn::where('board_id', $board->id)->findOrFail($data['column_id']);
        }
        $board->update(['billed_column_id' => $data['column_id'] ?? null]);
        return back()->with('success', 'Billed column updated.');
    }

    // ----- Comments ---------------------------------------------------------

    public function storeComment(Request $request, TaskCard $card)
    {
        if (!auth()->user()->canInWorkspace(app('current_workspace'), 'tasks.reply')) {
            abort(403, 'You do not have permission to comment.');
        }
        $this->authorizeView($card->board);
        $data = $request->validate(['body' => 'required|string|max:5000']);
        $comment = $card->comments()->create([
            'user_id' => auth()->id(),
            'body'    => $data['body'],
        ]);
        TaskActivity::log($card->id, auth()->id(), 'commented', ['preview' => mb_substr($data['body'], 0, 80)]);
        $this->notifyMentions($card, $data['body']);
        $comment->load('user:id,name,avatar');
        return response()->json(['ok' => true, 'comment' => $comment]);
    }

    /** Notify @name / @email mentions found in a comment to workspace members. */
    private function notifyMentions(TaskCard $card, string $body): void
    {
        if (!preg_match_all('/@([a-z0-9._\-]{2,64})/i', $body, $m)) return;
        $tokens = array_unique($m[1]);
        if (!$tokens) return;
        $members = $this->workspaceMembers();
        foreach ($tokens as $token) {
            $tokenLower = mb_strtolower($token);
            $hit = $members->first(function ($u) use ($tokenLower) {
                $name = mb_strtolower(preg_replace('/\s+/', '', (string) $u->name));
                $handle = mb_strtolower((string) ($u->handle ?? ''));
                $emailUser = mb_strtolower(strstr((string) $u->email, '@', true) ?: '');
                return $name === $tokenLower || $handle === $tokenLower || $emailUser === $tokenLower;
            });
            if (!$hit || (int) $hit->id === (int) auth()->id()) continue;
            UserNotification::create([
                'user_id'    => $hit->id,
                'type'       => 'task_mention',
                'data'       => [
                    'message'    => optional(auth()->user())->name . ' mentioned you on: ' . $card->title,
                    'card_id'    => $card->id,
                    'board_id'   => $card->board_id,
                    'board_name' => optional($card->board)->name,
                    'mentioner'  => optional(auth()->user())->name,
                    'url'        => route('user.tasks.show', $card->board_id) . '#card-' . $card->id,
                ],
                'created_at' => now(),
            ]);
        }
    }

    // ----- Attachments ------------------------------------------------------

    public function storeAttachment(Request $request, TaskCard $card)
    {
        $this->authorizeEdit($card->board);
        $request->validate([
            'file' => [
                'required', 'file', 'max:10240',
                'mimes:jpg,jpeg,png,gif,webp,bmp,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,md,rtf,zip,tar,gz,mp3,mp4,mov,wav,ogg',
            ],
        ]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (in_array($ext, ['html','htm','svg','xhtml','xml','js','mjs','php','phtml','phar','exe','sh','bat','cmd'], true)) {
            return response()->json(['ok' => false, 'error' => 'File type not allowed.'], 422);
        }
        $disk = config('filesystems.default');
        $path = $file->store('task-attachments/' . $card->workspace_id, $disk);
        $att = TaskAttachment::create([
            'card_id'             => $card->id,
            'uploaded_by_user_id' => auth()->id(),
            'original_name'       => $file->getClientOriginalName(),
            'mime'                => $file->getClientMimeType(),
            'size_bytes'          => $file->getSize(),
            'disk'                => $disk,
            'path'                => $path,
        ]);
        TaskActivity::log($card->id, auth()->id(), 'attached', ['name' => $att->original_name]);
        return response()->json([
            'ok' => true,
            'attachment' => array_merge($att->toArray(), [
                'url' => $att->url(),
                'human_size' => $att->humanSize(),
            ]),
        ]);
    }

    public function downloadAttachment(TaskAttachment $attachment)
    {
        $card = $this->resolveScopedCard($attachment->card_id);
        $this->authorizeView($card->board);
        $disk = \Storage::disk($attachment->disk);
        if (!$disk->exists($attachment->path)) {
            abort(404);
        }
        return $disk->download($attachment->path, $attachment->original_name, [
            'Content-Type'              => 'application/octet-stream',
            'X-Content-Type-Options'    => 'nosniff',
            'Content-Security-Policy'   => "default-src 'none'",
        ]);
    }

    public function destroyAttachment(TaskAttachment $attachment)
    {
        $card = $this->resolveScopedCard($attachment->card_id);
        $this->authorizeEdit($card->board);
        \Storage::disk($attachment->disk)->delete($attachment->path);
        TaskActivity::log($card->id, auth()->id(), 'attachment_removed', ['name' => $attachment->original_name]);
        $attachment->delete();
        return response()->json(['ok' => true]);
    }

    // ----- Labels -----------------------------------------------------------

    public function storeLabel(Request $request, TaskBoard $board)
    {
        $this->authorizeEdit($board);
        $data = $request->validate([
            'name'  => 'required|string|max:60',
            'color' => 'nullable|string|max:16',
        ]);
        $label = $board->labels()->create([
            'workspace_id' => $board->workspace_id,
            'name'         => $data['name'],
            'color'        => $data['color'] ?? '#8b5cf6',
        ]);
        return response()->json(['ok' => true, 'label' => $label]);
    }

    public function attachLabel(Request $request, TaskCard $card)
    {
        $this->authorizeEdit($card->board);
        $data = $request->validate(['label_id' => 'required|integer|exists:task_labels,id']);
        $label = TaskLabel::where('board_id', $card->board_id)->findOrFail($data['label_id']);
        if (!$card->labels()->where('label_id', $label->id)->exists()) {
            $card->labels()->attach($label->id);
            TaskActivity::log($card->id, auth()->id(), 'label_added', ['name' => $label->name]);
        }
        return response()->json(['ok' => true]);
    }

    public function detachLabel(TaskCard $card, TaskLabel $label)
    {
        $this->authorizeEdit($card->board);
        if ($card->labels()->where('label_id', $label->id)->exists()) {
            $card->labels()->detach($label->id);
            TaskActivity::log($card->id, auth()->id(), 'label_removed', ['name' => $label->name]);
        }
        return response()->json(['ok' => true]);
    }

    // ----- Helpers ----------------------------------------------------------

    private function createBoard(string $name, string $scope, ?string $color): TaskBoard
    {
        return DB::transaction(function () use ($name, $scope, $color) {
            $board = TaskBoard::create([
                'name'          => $name,
                'scope'         => $scope,
                'color'         => $color,
                'owner_user_id' => $scope === 'personal' ? auth()->id() : null,
            ]);
            foreach (self::STARTER_COLUMNS as $i => $col) {
                $board->columns()->create([
                    'workspace_id' => $board->workspace_id,
                    'name'         => $col['name'],
                    'color'        => $col['color'],
                    'is_done'      => $col['is_done'],
                    'position'     => $i + 1,
                ]);
            }
            return $board->fresh();
        });
    }

    private function authorizeView(TaskBoard $board): void
    {
        $user = auth()->user();
        if (!$board->visibleTo($user)) abort(404);
        if (!$user->canInWorkspace(app('current_workspace'), 'tasks.view')) abort(403);
    }

    private function authorizeCreate(TaskBoard $board): void
    {
        $user = auth()->user();
        if (!$board->visibleTo($user)) abort(404);
        // Personal-board owner may always create cards on their own board.
        if ($board->scope === 'personal' && (int) $board->owner_user_id === (int) $user->id) return;
        if (!$user->canInWorkspace(app('current_workspace'), 'tasks.create')) abort(403);
    }

    private function authorizeEdit(TaskBoard $board): void
    {
        $user = auth()->user();
        if (!$board->visibleTo($user)) abort(404);
        if ($board->scope === 'personal' && (int) $board->owner_user_id === (int) $user->id) return;
        if (!$user->canInWorkspace(app('current_workspace'), 'tasks.edit')) abort(403);
    }

    /** Delete gate for boards, cards, columns, comments, attachments. */
    private function authorizeDelete(TaskBoard $board): void
    {
        $user = auth()->user();
        if (!$board->visibleTo($user)) abort(404);
        if ($board->scope === 'personal' && (int) $board->owner_user_id === (int) $user->id) return;
        $ws = app('current_workspace');
        if ($user->canInWorkspace($ws, 'tasks.delete') || $user->canInWorkspace($ws, 'tasks.edit')) return;
        abort(403);
    }

    /** All members visible for assignment in the current workspace (owner + members). */
    private function workspaceMembers()
    {
        $ws = app('current_workspace');
        $memberIds = WorkspaceMember::where('workspace_id', $ws->id)->pluck('user_id')->all();
        $memberIds[] = $ws->owner_user_id;
        return User::whereIn('id', array_unique($memberIds))->select('id','name','avatar','email')->get();
    }

    private function isWorkspaceMember(int $userId, int $workspaceId): bool
    {
        $ws = \App\Modules\User\Models\Workspace::find($workspaceId);
        if (!$ws) return false;
        if ((int) $ws->owner_user_id === $userId) return true;
        return WorkspaceMember::where('workspace_id', $workspaceId)
            ->where('user_id', $userId)
            ->exists();
    }

    private function notifyAssignment(TaskCard $card, int $assigneeId): void
    {
        if ($assigneeId === auth()->id()) return; // assigning yourself — no ping
        UserNotification::create([
            'user_id'    => $assigneeId,
            'type'       => 'task_assigned',
            'data'       => [
                'message'    => 'You were assigned to a task: ' . $card->title,
                'card_id'    => $card->id,
                'board_id'   => $card->board_id,
                'board_name' => optional($card->board)->name,
                'assigner'   => optional(auth()->user())->name,
                'url'        => route('user.tasks.show', $card->board_id) . '#card-' . $card->id,
            ],
            'created_at' => now(),
        ]);
    }
}
