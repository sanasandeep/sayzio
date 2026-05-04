<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Models\WorkspaceActivityEvent;
use App\Modules\User\Services\WorkspaceActivityRecorder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Per-member workspace activity log: paginated, filterable list of who did
 * what inside the active workspace, plus a CSV export of the filtered view.
 *
 * Read-only / audit; restricted to the workspace owner and members holding
 * the Admin role (same gate as the Team page).
 */
class WorkspaceActivityController extends Controller
{
    protected function workspace(Request $request): Workspace
    {
        $ws = app('current_workspace');
        $user = $request->user();
        $isAdmin = false;
        if ($user) {
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                $isAdmin = true;
            } elseif ((int) $ws->owner_user_id === (int) $user->id) {
                $isAdmin = true;
            } else {
                $m = $user->membershipFor($ws);
                $isAdmin = $m && $m->role === 'admin';
            }
        }
        abort_unless($isAdmin, 403, 'Only the workspace owner or an Admin can view the activity log.');
        return $ws;
    }

    public function index(Request $request)
    {
        $ws = $this->workspace($request);
        $query = $this->buildQuery($ws, $request);

        $events = $query->orderByDesc('id')->paginate(50)->withQueryString();

        // Resolve actor names in one query for the rendered page.
        $actorIds = $events->pluck('actor_user_id')->filter()->unique()->values();
        $actors = User::whereIn('id', $actorIds)->get(['id', 'name', 'email', 'avatar'])->keyBy('id');

        // Members + the owner make up the "by member" filter options.
        $members = $ws->members()->with('user:id,name,email')->get()
            ->map(fn ($m) => ['id' => (int) $m->user_id, 'name' => $m->user->name ?? $m->user->email ?? '#' . $m->user_id])
            ->values()
            ->all();
        if ($ws->owner) {
            array_unshift($members, [
                'id'   => (int) $ws->owner->id,
                'name' => ($ws->owner->name ?: $ws->owner->email) . ' (owner)',
            ]);
        }

        return view('user.workspaces.activity', [
            'workspace'   => $ws,
            'events'      => $events,
            'actors'      => $actors,
            'members'     => $members,
            'actionList'  => WorkspaceActivityRecorder::ACTIONS,
            'objectTypes' => WorkspaceActivityRecorder::OBJECT_TYPES,
            'filters'     => $this->currentFilters($request),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $ws = $this->workspace($request);
        $query = $this->buildQuery($ws, $request);

        $filename = 'activity-' . $ws->id . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Timestamp (UTC)', 'Actor', 'Action', 'Object type', 'Object', 'Object URL', 'IP', 'User agent', 'Payload']);

            $query->orderByDesc('id')->chunk(500, function ($rows) use ($out) {
                $actorIds = $rows->pluck('actor_user_id')->filter()->unique();
                $actors = User::whereIn('id', $actorIds)->get(['id', 'name', 'email'])->keyBy('id');
                foreach ($rows as $r) {
                    $actor = $r->actor_user_id ? ($actors[$r->actor_user_id] ?? null) : null;
                    fputcsv($out, [
                        optional($r->created_at)->toIso8601String(),
                        $actor ? ($actor->name ?: $actor->email) : ('user#' . ($r->actor_user_id ?? '?')),
                        $r->action,
                        $r->object_type,
                        $r->object_label ?: ($r->object_id ? '#' . $r->object_id : ''),
                        $r->object_url,
                        $r->ip,
                        $r->user_agent,
                        $r->payload ? json_encode($r->payload, JSON_UNESCAPED_SLASHES) : '',
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function currentFilters(Request $request): array
    {
        return [
            'member'      => $request->input('member'),
            'action'      => $request->input('action'),
            'object_type' => $request->input('object_type'),
            'from'        => $request->input('from'),
            'to'          => $request->input('to'),
            'q'           => $request->input('q'),
        ];
    }

    protected function buildQuery(Workspace $ws, Request $request)
    {
        $q = WorkspaceActivityEvent::query()->where('workspace_id', $ws->id);

        if ($member = $request->input('member')) {
            $q->where('actor_user_id', (int) $member);
        }
        if ($action = $request->input('action')) {
            $q->where('action', $action);
        }
        if ($otype = $request->input('object_type')) {
            $q->where('object_type', $otype);
        }
        if ($from = $request->input('from')) {
            try { $q->where('created_at', '>=', \Carbon\Carbon::parse($from)->startOfDay()); }
            catch (\Throwable $e) {}
        }
        if ($to = $request->input('to')) {
            try { $q->where('created_at', '<=', \Carbon\Carbon::parse($to)->endOfDay()); }
            catch (\Throwable $e) {}
        }
        if ($needle = trim((string) $request->input('q'))) {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $needle) . '%';
            $q->where(function ($w) use ($like) {
                $w->where('object_label', 'like', $like)
                  ->orWhere('action', 'like', $like)
                  ->orWhere('ip', 'like', $like);
            });
        }
        return $q;
    }
}
