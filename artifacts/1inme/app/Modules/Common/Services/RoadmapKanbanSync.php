<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\RoadmapItem;
use App\Modules\User\Models\TaskActivity;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskColumn;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Two-way bridge between a public RoadmapItem and a workspace TaskCard.
 *
 * The RoadmapItem is the source of truth for the *public* status that
 * fans see on the biolink. The linked TaskCard is the source of truth
 * for the *internal* progress columns the team uses. A change in
 * either place is mirrored to the other through the methods below;
 * both sides keep their own sort order and metadata so we don't fight
 * each kanban's user-driven layout.
 */
class RoadmapKanbanSync
{
    /**
     * Map of public roadmap statuses -> case-insensitive column name
     * fragments we'll match on the linked task board, in priority order.
     * The first column whose name contains any of the fragments wins.
     */
    public const STATUS_TO_COLUMN_HINTS = [
        'ideas'       => ['idea', 'backlog', 'todo', 'to do', 'inbox'],
        'planned'     => ['planned', 'next', 'upcoming', 'queued'],
        'in_progress' => ['in progress', 'doing', 'wip', 'in-progress'],
        'shipped'     => ['shipped', 'done', 'released', 'launched', 'complete'],
    ];

    /**
     * If the item already has a linked card, return it. Otherwise spawn
     * a new card on the configured board (board id taken from the
     * block's settings.kanban_board_id) and link it.
     *
     * Returns null when no board is configured or the board can't be
     * resolved — caller treats that as "approve without sync".
     */
    public function ensureCardForItem(RoadmapItem $item): ?TaskCard
    {
        // Lock the item row up front so concurrent calls (e.g. a creator
        // double-clicking "approve" or two admins racing each other) can't
        // each spawn a fresh card for the same idea.
        return DB::transaction(function () use ($item) {
            $locked = RoadmapItem::query()->withoutGlobalScope('workspace')
                ->lockForUpdate()->find($item->id);
            if (!$locked) return null;

            if ($locked->task_card_id) {
                $existing = TaskCard::query()->withoutGlobalScope('workspace')->find($locked->task_card_id);
                if ($existing) return $existing;
            }

            $boardId = (int) data_get($locked->block?->settings ?? [], 'kanban_board_id');
            if ($boardId <= 0) return null;

            $board = TaskBoard::query()->withoutGlobalScope('workspace')
                ->where('id', $boardId)
                ->where('workspace_id', $locked->workspace_id)
                ->first();
            if (!$board) return null;

            $column = $this->columnForStatus($board, $locked->status) ?? $board->columns()->first();
            if (!$column) return null;

            $maxPos = TaskCard::query()->withoutGlobalScope('workspace')
                ->where('column_id', $column->id)
                ->max('position');

            $card = new TaskCard();
            $card->workspace_id    = $locked->workspace_id;
            $card->board_id        = $board->id;
            $card->column_id       = $column->id;
            $card->title           = Str::limit($locked->title, 180, '');
            $card->description     = trim(($locked->description ?? '') . "\n\n— From public roadmap (" . $locked->votes_count . ' upvotes)');
            $card->position        = (int) $maxPos + 1;
            $card->priority        = $locked->votes_count >= 10 ? 'high' : 'normal';
            $card->roadmap_item_id = $locked->id;
            $card->save();

            $locked->task_card_id = $card->id;
            $locked->save();
            $item->task_card_id = $card->id;

            TaskActivity::log($card->id, null, 'roadmap_linked', [
                'item_id'     => $locked->id,
                'votes_count' => $locked->votes_count,
                'link_alias'  => $locked->link?->alias,
            ]);

            return $card;
        });
    }

    /**
     * Roadmap -> kanban: when a creator changes the public status,
     * move the linked card to the column that best matches the new
     * status. No-op if there's no linked card.
     */
    public function pushStatusToCard(RoadmapItem $item): void
    {
        if (!$item->task_card_id) return;
        $card = TaskCard::query()->withoutGlobalScope('workspace')->find($item->task_card_id);
        if (!$card) return;

        $board = TaskBoard::query()->withoutGlobalScope('workspace')->find($card->board_id);
        if (!$board) return;

        $column = $this->columnForStatus($board, $item->status);
        if (!$column || $column->id === $card->column_id) return;

        DB::transaction(function () use ($card, $column, $item) {
            $maxPos = TaskCard::query()->withoutGlobalScope('workspace')
                ->where('column_id', $column->id)
                ->max('position');
            $oldColumnId = $card->column_id;
            $card->column_id = $column->id;
            $card->position  = (int) $maxPos + 1;
            if ($column->is_done && !$card->completed_at) $card->completed_at = now();
            if (!$column->is_done && $card->completed_at)  $card->completed_at = null;
            $card->save();

            TaskActivity::log($card->id, null, 'roadmap_status', [
                'from_column_id' => $oldColumnId,
                'to_column_id'   => $column->id,
                'roadmap_status' => $item->status,
                'item_id'        => $item->id,
            ]);
        });
    }

    /**
     * Kanban -> roadmap: when a card moves between columns, infer the
     * matching public roadmap status and update the linked item. Called
     * from TaskBoardController::moveCard via the observer on TaskCard.
     */
    public function pullStatusFromCard(TaskCard $card): void
    {
        if (!$card->roadmap_item_id) return;
        $item = RoadmapItem::query()->withoutGlobalScope('workspace')->find($card->roadmap_item_id);
        if (!$item) return;

        $column = TaskColumn::query()->withoutGlobalScope('workspace')->find($card->column_id);
        if (!$column) return;

        $newStatus = $this->statusForColumnName((string) $column->name);
        if (!$newStatus || $newStatus === $item->status) return;

        $oldStatus = $item->status;
        $item->status = $newStatus;
        if ($newStatus === 'shipped' && !$item->shipped_at) {
            $item->shipped_at = now();
        }
        $item->save();

        // Notify upvoters when a card-driven move ships an idea.
        if ($newStatus === 'shipped' && $oldStatus !== 'shipped') {
            app(\App\Modules\Common\Services\RoadmapNotifier::class)->notifyShipped($item);
        }
    }

    /** Find the best column on $board to host a given roadmap status. */
    public function columnForStatus(TaskBoard $board, string $status): ?TaskColumn
    {
        $hints = self::STATUS_TO_COLUMN_HINTS[$status] ?? [];
        if (empty($hints)) return null;
        $columns = $board->columns()->get();
        foreach ($hints as $needle) {
            foreach ($columns as $col) {
                if (Str::contains(Str::lower($col->name), Str::lower($needle))) return $col;
            }
        }
        return null;
    }

    /** Reverse: derive a roadmap status from a column name string. */
    public function statusForColumnName(string $name): ?string
    {
        $name = Str::lower($name);
        foreach (self::STATUS_TO_COLUMN_HINTS as $status => $hints) {
            foreach ($hints as $needle) {
                if (Str::contains($name, Str::lower($needle))) return $status;
            }
        }
        return null;
    }
}
