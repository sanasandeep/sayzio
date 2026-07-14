<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Illuminate\Support\Facades\DB;

/**
 * Creates the starter "My Tasks" personal kanban board (and its starter
 * columns) on a user's personal workspace if they don't already have one.
 *
 * Used both by the User::created hook (per-account provisioning) and by
 * the v1 backfill migration (existing accounts).
 */
class PersonalTaskBoardProvisioner
{
    public static function ensureFor(User $user): ?TaskBoard
    {
        // Find their personal workspace, or provision one on the fly when
        // the User::created hook fires before any workspace exists.
        $ws = Workspace::query()
            ->where('owner_user_id', $user->id)
            ->where(function ($q) {
                $q->where('is_personal', true)->orWhereNull('is_personal');
            })
            ->orderBy('id')
            ->first();
        if (!$ws && method_exists($user, 'ensureDefaultWorkspace')) {
            $ws = $user->ensureDefaultWorkspace();
        }
        if (!$ws) return null;

        $existing = TaskBoard::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $ws->id)
            ->where('owner_user_id', $user->id)
            ->where('scope', 'personal')
            ->first();
        if ($existing) return $existing;

        return DB::transaction(function () use ($ws, $user) {
            $board = new TaskBoard();
            $board->workspace_id  = $ws->id;
            $board->owner_user_id = $user->id;
            $board->scope         = 'personal';
            $board->name          = 'My Tasks';
            $board->color         = '#3d6bff'; // default board color = brand accent (blue)
            $board->position      = 0;
            $board->save();

            $cols = [
                ['name' => 'Todo',  'color' => '#64748b', 'is_done' => false],
                ['name' => 'Doing', 'color' => '#3b82f6', 'is_done' => false],
                ['name' => 'Done',  'color' => '#10b981', 'is_done' => true ],
            ];
            foreach ($cols as $i => $c) {
                $board->columns()->create([
                    'workspace_id' => $ws->id,
                    'name'         => $c['name'],
                    'color'        => $c['color'],
                    'is_done'      => $c['is_done'],
                    'position'     => $i + 1,
                ]);
            }
            return $board->fresh();
        });
    }
}
