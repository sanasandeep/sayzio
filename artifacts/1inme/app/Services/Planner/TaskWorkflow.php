<?php

namespace App\Services\Planner;

use App\Modules\Common\Services\RoadmapKanbanSync;
use App\Modules\User\Models\TaskActivity;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskColumn;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;

class TaskWorkflow
{
    public function matches(TaskCard $card, array $rule): bool
    {
        return match ($rule['condition']) {
            'always' => true,'priority' => $card->priority === ($rule['value'] ?? null),'label' => $card->labels()->where('task_labels.id', $rule['value'] ?? 0)->exists(),default => false
        };
    }

    public function complete(TaskCard $card, bool $completed): TaskCard
    {
        return DB::transaction(function () use ($card, $completed) {
            TaskBoard::whereKey($card->board_id)->lockForUpdate()->firstOrFail();
            $card = TaskCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            $rules = collect($card->column->transitions ?? [])->where('trigger', 'completed');
            $rule = $rules->first(fn ($r) => $this->matches($card, $r));
            if ($completed && $rules->isNotEmpty()) {
                abort_unless($rule, 422, 'No completion route matches this task. Update its category/priority or choose an allowed destination.');
                $target = TaskColumn::where('board_id', $card->board_id)->findOrFail($rule['target_column_id']);
            } else {
                $target = TaskColumn::where('board_id', $card->board_id)->where('is_done', $completed)->orderBy('position')->first();
                if (! $target && $completed) {
                    $target = $card->board->columns()->create(['workspace_id' => $card->workspace_id, 'name' => 'Done', 'is_done' => true, 'position' => (int) $card->board->columns()->max('position') + 1]);
                }
            }
            abort_unless($target, 422, 'Add a work column before reopening this task.');
            if ($completed) {
                $card->update(['progress' => 100]);
            }

            return $this->move($card, $target, 0, false);
        });
    }

    public function move(TaskCard $card, TaskColumn $target, int $position, bool $manual = true): TaskCard
    {
        return DB::transaction(function () use ($card, $target, $position, $manual) {
            TaskBoard::whereKey($card->board_id)->lockForUpdate()->firstOrFail();
            $card = TaskCard::whereKey($card->id)->lockForUpdate()->firstOrFail();
            abort_unless($target->board_id === $card->board_id, 404);
            $from = $card->column_id;
            $wasCompleted = (bool) $card->completed_at;
            if ($manual && $from !== $target->id) {
                $routes = collect($card->column->transitions ?? [])->where('trigger', 'manual');
                if ($routes->isNotEmpty()) {
                    abort_unless($routes->contains(fn ($r) => (int) $r['target_column_id'] === $target->id && $this->matches($card, $r)), 422, 'This move does not match an allowed manual route.');
                }
            }
            $siblings = TaskCard::where('column_id', $target->id)->whereNull('archived_at')->where('id', '!=', $card->id)->orderBy('position')->get();
            $ordered = $siblings->all();
            array_splice($ordered, min(max(0, $position), count($ordered)), 0, [$card]);
            foreach ($ordered as $i => $row) {
                TaskCard::whereKey($row->id)->update(['column_id' => $target->id, 'position' => $i + 1]);
            }
            $card->refresh();
            $card->update(['completed_at' => $target->is_done ? ($card->completed_at ?? now()) : null, 'progress' => $target->is_done ? 100 : ($card->completed_at || $card->progress === 100 ? 0 : $card->progress)]);
            if ($card->completed_at) {
                $entries = $card->timeEntries()->where('source', 'timer')->whereNull('ended_at')->orderBy('user_id')->get();
                foreach ($entries->pluck('user_id')->unique() as $userId) {
                    User::whereKey($userId)->lockForUpdate()->firstOrFail();
                }
                foreach ($entries as $entry) {
                    $end = now()->max($entry->started_at);
                    $entry->update(['ended_at' => $end, 'minutes' => max(0, (int) ceil($entry->started_at->diffInSeconds($end) / 60))]);
                }
                DB::table('task_tracking_preferences')->where('workspace_id', $card->workspace_id)->where('card_id', $card->id)->delete();
            }
            if ($wasCompleted !== (bool) $card->completed_at) {
                TaskActivity::log($card->id, auth()->id(), $card->completed_at ? 'completed' : 'reopened', []);
            }
            if ($from !== $target->id) {
                TaskActivity::log($card->id, auth()->id(), 'moved', ['from' => $from, 'to' => $target->id, 'to_name' => $target->name]);
            }
            if ($card->roadmap_item_id) {
                app(RoadmapKanbanSync::class)->pullStatusFromCard($card->fresh());
            }

            return $card->fresh();
        });
    }
}
