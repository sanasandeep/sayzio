<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\BiolinkReport;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BiolinkReportController extends Controller
{
    /**
     * Aggregated moderation queue. We group by link so admins act on
     * the underlying biolink — not on individual report rows.
     */
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');
        $reason = $request->get('reason');

        $base = BiolinkReport::query();
        if (in_array($status, ['pending', 'dismissed', 'warned', 'hidden', 'escalated'], true)) {
            $base->where('status', $status);
        }
        if ($reason && array_key_exists($reason, BiolinkReport::REASONS)) {
            $base->where('reason', $reason);
        }

        // Aggregate per link for the queue view. We surface counts +
        // distinct reasons + most-recent comment so admins triage fast.
        $rows = (clone $base)
            ->select('link_id')
            ->selectRaw('SUM(coalesced_count) as total_signals')
            ->selectRaw('COUNT(*) as report_count')
            ->selectRaw('MAX(created_at) as last_report_at')
            ->groupBy('link_id')
            ->orderByDesc('last_report_at')
            ->paginate(25)
            ->withQueryString();

        $linkIds = $rows->pluck('link_id')->all();
        $links = Link::whereIn('id', $linkIds)->with('user')->get()->keyBy('id');

        // Pull the report rows for the visible page so we can show
        // reason breakdowns + the latest comment without an N+1.
        $reportsByLink = (clone $base)
            ->whereIn('link_id', $linkIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('link_id');

        return view('admin.biolink-reports.index', compact(
            'rows', 'links', 'reportsByLink', 'status', 'reason'
        ));
    }

    public function dismiss(Request $request, Link $link)
    {
        $this->actionOnLink($link, 'dismissed', $request->input('note'));
        return back()->with('success', 'Reports dismissed.');
    }

    public function warn(Request $request, Link $link)
    {
        $reason = $this->dominantReason($link);
        $note = $request->input('note');
        $this->actionOnLink($link, 'warned', $note);
        $link->forceFill([
            'moderation_state'  => 'warned',
            'moderation_reason' => $reason,
            'moderation_note'   => $note,
            'moderation_at'     => now(),
            'moderation_appealed_at'    => null,
            'moderation_appeal_message' => null,
        ])->save();
        $this->notifyCreator($link, 'biolink_warned', $reason, $note);
        return back()->with('success', 'Creator warned.');
    }

    public function hide(Request $request, Link $link)
    {
        $reason = $this->dominantReason($link);
        $note = $request->input('note');
        $this->actionOnLink($link, 'hidden', $note);
        $link->forceFill([
            'moderation_state'  => 'hidden',
            'moderation_reason' => $reason,
            'moderation_note'   => $note,
            'moderation_at'     => now(),
            'moderation_appealed_at'    => null,
            'moderation_appeal_message' => null,
        ])->save();
        $this->notifyCreator($link, 'biolink_hidden', $reason, $note);
        return back()->with('success', 'Link in Bio hidden.');
    }

    public function escalate(Request $request, Link $link)
    {
        $note = $request->input('note');
        $this->actionOnLink($link, 'escalated', $note);
        $link->forceFill([
            'moderation_state' => 'escalated',
            'moderation_note'  => $note,
            'moderation_at'    => now(),
        ])->save();
        return back()->with('success', 'Escalated for senior review.');
    }

    public function restore(Request $request, Link $link)
    {
        $link->forceFill([
            'moderation_state'  => null,
            'moderation_reason' => null,
            'moderation_note'   => null,
            'moderation_at'     => null,
            'moderation_appealed_at'    => null,
            'moderation_appeal_message' => null,
        ])->save();

        BiolinkReport::where('link_id', $link->id)
            ->whereIn('status', ['warned', 'hidden', 'escalated', 'pending'])
            ->update(['status' => 'dismissed', 'actioned_at' => now()]);

        $this->notifyCreator($link, 'biolink_restored', null, null);
        return back()->with('success', 'Link in Bio restored.');
    }

    /**
     * Update every pending report row for the link to the new status
     * and stamp the action time. Existing non-pending rows (history
     * from a prior decision) are left alone.
     */
    protected function actionOnLink(Link $link, string $status, ?string $note): void
    {
        BiolinkReport::where('link_id', $link->id)
            ->where('status', 'pending')
            ->update([
                'status'      => $status,
                'actioned_at' => now(),
                'admin_note'  => $note ? mb_substr($note, 0, 1000) : null,
            ]);
    }

    /**
     * Pick the most-cited reason across this link's pending reports
     * (used to populate the creator-facing notification + page text).
     */
    protected function dominantReason(Link $link): ?string
    {
        $row = BiolinkReport::where('link_id', $link->id)
            ->where('status', 'pending')
            ->select('reason', DB::raw('SUM(coalesced_count) as weight'))
            ->groupBy('reason')
            ->orderByDesc('weight')
            ->first();
        return $row?->reason;
    }

    protected function notifyCreator(Link $link, string $type, ?string $reason, ?string $note): void
    {
        if (!$link->user_id) return;
        $reasonLabel = $reason ? (BiolinkReport::REASONS[$reason] ?? $reason) : null;

        $messages = [
            'biolink_warned'  => "Your Link in Bio \"{$link->title}\" was reported and reviewed by our team. Please update any content that violates our policies.",
            'biolink_hidden'  => "Your Link in Bio \"{$link->title}\" has been hidden after review of visitor reports.",
            'biolink_restored'=> "Good news — your Link in Bio \"{$link->title}\" has been restored.",
        ];

        UserNotification::create([
            'user_id' => $link->user_id,
            'type'    => $type,
            'data'    => [
                'message'        => $messages[$type] ?? 'Your Link in Bio was actioned by moderation.',
                'link_id'        => $link->id,
                'link_title'     => $link->title,
                'link_alias'     => $link->alias,
                'reason'         => $reason,
                'reason_label'   => $reasonLabel,
                'admin_note'     => $note,
                'edit_url'       => route('user.links.edit', $link),
            ],
            'created_at' => now(),
        ]);
    }
}
