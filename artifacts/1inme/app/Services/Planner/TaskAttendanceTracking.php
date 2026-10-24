<?php

namespace App\Services\Planner;

use App\Modules\User\Models\AttendanceParticipant;
use App\Modules\User\Models\TaskCard;
use App\Modules\User\Models\TaskTimeEntry;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TaskAttendanceTracking
{
    public function close(AttendanceParticipant $person, $at): void
    {
        TaskTimeEntry::withoutWorkspaceScope()->where('attendance_participant_id', $person->id)->whereNull('ended_at')->whereNotNull('started_at')->get()->each(function ($entry) use ($at) {
            $end = Carbon::parse($at)->max($entry->started_at);
            $entry->update(['ended_at' => $end, 'minutes' => max(0, (int) ceil($entry->started_at->diffInSeconds($end) / 60))]);
        });
    }

    public function punch(AttendanceParticipant $person, string $action, $at): void
    {
        if ((int) $person->workspace_id === 0) {
            return;
        }
        User::whereKey($person->user_id)->lockForUpdate()->firstOrFail();
        if (in_array($action, ['out', 'break'])) {
            $this->close($person, $at);

            return;
        }
        if (! in_array($action, ['in', 'resume'])) {
            return;
        }
        $pref = DB::table('task_tracking_preferences')->where('workspace_id', $person->workspace_id)->where('user_id', $person->user_id)->first();
        if (! $pref) {
            return;
        }
        $user = User::find($person->user_id);
        $ws = Workspace::find($person->workspace_id);
        if (! $user || ! $ws) {
            return;
        }$member = $user->membershipFor($ws);
        if (! $user || ! $ws || ((int) $ws->owner_user_id !== $user->id && (! $member || $member->isSuspended())) || ! $user->canInWorkspace($ws, 'tasks.track')) {
            return;
        }
        $card = TaskCard::withoutWorkspaceScope()->where('workspace_id', $ws->id)->whereNull('archived_at')->whereNull('completed_at')->find($pref->card_id);
        if (! $card || ! $card->board->visibleTo($user) || TaskTimeEntry::withoutWorkspaceScope()->where('user_id', $user->id)->whereNull('ended_at')->whereNotNull('started_at')->exists()) {
            return;
        }
        TaskTimeEntry::create(['workspace_id' => $ws->id, 'card_id' => $card->id, 'user_id' => $user->id, 'started_at' => $at, 'source' => 'timer', 'attendance_participant_id' => $person->id]);
    }
}
