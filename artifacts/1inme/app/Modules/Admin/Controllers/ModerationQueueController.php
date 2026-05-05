<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\DmcaTakedown;
use App\Modules\Common\Models\UserReport;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorPostComment;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Extended moderation queue (Task #1211). One screen with two tabs:
 *  - Reports — user_reports rows (creators / posts / comments / DMs)
 *  - DMCA   — dmca_takedowns rows
 *
 * Mirrors the existing BiolinkReport admin queue's interaction model
 * (status pills, dismiss/warn/remove actions, admin-note field).
 */
class ModerationQueueController extends Controller
{
    public function index(Request $request)
    {
        $tab        = $request->query('tab', 'reports');
        $status     = $request->query('status', 'pending');
        $type       = $request->query('type', '');
        $reason     = $request->query('reason', '');
        $search     = trim((string) $request->query('q', ''));

        if ($tab === 'dmca') {
            $rows = DmcaTakedown::query()
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($search !== '', function ($q) use ($search) {
                    $like = '%' . $search . '%';
                    $q->where(function ($w) use ($like) {
                        $w->where('reporter_email', 'ilike', $like)
                          ->orWhere('reporter_name', 'ilike', $like)
                          ->orWhere('infringing_url', 'ilike', $like);
                    });
                })
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString();
        } else {
            $rows = UserReport::query()
                ->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->when($type !== '',     fn ($q) => $q->where('target_type', $type))
                ->when($reason !== '',   fn ($q) => $q->where('reason', $reason))
                ->when($search !== '',   function ($q) use ($search) {
                    $q->where('comment', 'ilike', '%' . $search . '%');
                })
                ->orderByDesc('coalesced_count')
                ->orderByDesc('id')
                ->paginate(25)
                ->withQueryString();
            $rows->getCollection()->transform(function ($row) {
                $row->target_label   = $this->describeTarget($row);
                return $row;
            });
        }

        return view('admin.moderation-queue.index', [
            'tab'         => $tab,
            'status'      => $status,
            'type'        => $type,
            'reason'      => $reason,
            'search'      => $search,
            'rows'        => $rows,
            'reportTypes' => UserReport::TARGET_TYPES,
            'reasons'     => UserReport::REASONS,
            'statuses'    => UserReport::STATUSES,
            'dmcaStatuses'=> DmcaTakedown::STATUSES,
            'pendingCounts' => [
                'reports' => UserReport::where('status', 'pending')->count(),
                'dmca'    => DmcaTakedown::where('status', 'pending')->count(),
            ],
        ]);
    }

    /**
     * Apply an action to a user_report. Actions:
     *   dismiss   — close as not actionable
     *   warn      — record warning (no content change)
     *   remove    — soft-delete / hide the offending content
     *   suspend   — suspend the offending creator
     *   escalate  — flag for senior review
     */
    public function actUserReport(Request $request, UserReport $report)
    {
        $data = $request->validate([
            'action'     => 'required|string|in:dismiss,warn,remove,suspend,escalate',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $admin = auth()->user();
        DB::transaction(function () use ($report, $data, $admin) {
            $action = $data['action'];

            if ($action === 'remove') {
                $this->removeTarget($report);
                $report->status = 'removed';
            } elseif ($action === 'suspend') {
                $this->suspendCreatorByReport($report);
                $report->status = 'suspended';
            } elseif ($action === 'warn') {
                $report->status = 'warned';
            } elseif ($action === 'escalate') {
                $report->status = 'escalated';
            } else {
                $report->status = 'dismissed';
            }
            $report->admin_note = $data['admin_note'] ?? $report->admin_note;
            $report->actioned_at = now();
            $report->actioned_by_user_id = $admin?->id;
            $report->save();
        });

        return back()->with('success', 'Report updated.');
    }

    /**
     * Apply an action to a DMCA takedown.
     *   valid    — record as legitimate (does NOT remove on its own)
     *   removed  — also hide / unpublish the post and creator-notify
     *   invalid  — rejected
     *   counter  — counter-notice received
     */
    public function actDmca(Request $request, DmcaTakedown $dmca)
    {
        $data = $request->validate([
            'action'     => 'required|string|in:valid,invalid,removed,counter',
            'admin_note' => 'nullable|string|max:1000',
        ]);

        $admin = auth()->user();
        DB::transaction(function () use ($dmca, $data, $admin) {
            if ($data['action'] === 'removed' && $dmca->target_post_id) {
                CreatorPost::query()->withoutGlobalScope('workspace')
                    ->whereKey($dmca->target_post_id)
                    ->update(['published_at' => null]);
            }
            $dmca->status = $data['action'];
            $dmca->admin_note = $data['admin_note'] ?? $dmca->admin_note;
            $dmca->actioned_at = now();
            $dmca->actioned_by_user_id = $admin?->id;
            $dmca->save();
        });

        return back()->with('success', 'DMCA takedown updated.');
    }

    /** Build a human-readable label for the report's target. */
    protected function describeTarget(UserReport $row): string
    {
        switch ($row->target_type) {
            case 'user':
                $u = User::find($row->target_id);
                return $u ? '@' . ($u->handle ?: $u->name) : "user #{$row->target_id}";
            case 'post':
                $p = CreatorPost::query()->withoutGlobalScope('workspace')->find($row->target_id);
                return $p ? 'Post #' . $p->id . ($p->title ? ' — ' . $p->title : '') : "post #{$row->target_id}";
            case 'comment':
                $c = CreatorPostComment::find($row->target_id);
                return $c ? 'Comment #' . $c->id : "comment #{$row->target_id}";
            case 'message':
                return "DM #{$row->target_id}";
        }
        return $row->target_type . ' #' . $row->target_id;
    }

    /** Hide / unpublish the offending content for a "remove" action. */
    protected function removeTarget(UserReport $row): void
    {
        switch ($row->target_type) {
            case 'post':
                CreatorPost::query()->withoutGlobalScope('workspace')
                    ->whereKey($row->target_id)->update(['published_at' => null]);
                break;
            case 'comment':
                CreatorPostComment::whereKey($row->target_id)->update(['status' => 'hidden']);
                break;
            case 'user':
                $u = User::find($row->target_id);
                if ($u) {
                    $u->profile_published = false;
                    $u->discoverable = false;
                    $u->save();
                }
                break;
        }
    }

    /**
     * For "suspend" we hide the creator and unpublish all posts. The
     * full account-suspension workflow lives in the user-management
     * area; here we apply the minimum to take the surface offline.
     */
    protected function suspendCreatorByReport(UserReport $row): void
    {
        $creatorId = null;
        if ($row->target_type === 'user') {
            $creatorId = $row->target_id;
        } elseif ($row->target_type === 'post') {
            $p = CreatorPost::query()->withoutGlobalScope('workspace')->find($row->target_id);
            $creatorId = $p?->user_id;
        } elseif ($row->target_type === 'comment') {
            $c = CreatorPostComment::find($row->target_id);
            $p = $c ? CreatorPost::query()->withoutGlobalScope('workspace')->find($c->post_id) : null;
            $creatorId = $p?->user_id;
        }
        if (!$creatorId) return;
        $u = User::find($creatorId);
        if (!$u) return;
        $u->profile_published = false;
        $u->discoverable = false;
        $u->save();
        CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('user_id', $u->id)->update(['published_at' => null]);
    }
}
