<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Services\LeadAggregator;
use App\Modules\User\Services\LeadApprover;
use Illuminate\Http\Request;

/**
 * The Leads review queue: a creator reviews people captured
 * across RSVPs, form submissions, subscribers, store/restaurant orders,
 * service bookings, reviews, and event-interest, then approves them into
 * Contacts (deduped, plan-cap-gated) or dismisses them.
 */
class LeadController extends Controller
{
    public function __construct(protected LeadApprover $approver) {}

    public function index(Request $request)
    {
        $owner = $this->owner($request);
        $aggregator = new LeadAggregator($owner->id);

        $filters = [
            'source' => $request->query('source') ?: null,
            'q'      => $request->query('q', ''),
        ];

        $leads = $aggregator->paginate($filters, 25, (int) $request->query('page', 1));
        $counts = $aggregator->countsBySource();

        $data = [
            'leads'        => $leads,
            'sourceLabels' => LeadAggregator::sourceLabels(),
            'counts'       => $counts,
            'totalPending' => array_sum($counts),
            'filters'      => $filters,
        ];

        if ($request->ajax()) {
            return response()->json([
                'html'  => view('user.leads._list', $data)->render(),
                'total' => $data['totalPending'],
            ]);
        }

        return view('user.leads.index', $data);
    }

    public function approve(Request $request)
    {
        $request->validate([
            'source_type' => 'required|string',
            'source_id'   => 'required|integer',
        ]);

        $owner = $this->owner($request);
        $aggregator = new LeadAggregator($owner->id);
        $item = $aggregator->find($request->input('source_type'), (int) $request->input('source_id'));

        if (!$item) {
            return $this->respond($request, false, 'That lead is no longer pending.');
        }

        try {
            $result = $this->approver->approve($owner, $item, $request->user()?->id);
        } catch (\RuntimeException $e) {
            return $this->respond($request, false, $e->getMessage());
        }

        $message = $result['result'] === 'merged'
            ? 'Merged into an existing contact.'
            : 'Added to your contacts.';

        return $this->respond($request, true, $message, [
            'contact_id'  => $result['contact']->id,
            'contact_url' => route('user.contacts.show', $result['contact']->id),
        ]);
    }

    public function dismiss(Request $request)
    {
        $request->validate([
            'source_type' => 'required|string',
            'source_id'   => 'required|integer',
        ]);

        $owner = $this->owner($request);
        $aggregator = new LeadAggregator($owner->id);
        $item = $aggregator->find($request->input('source_type'), (int) $request->input('source_id'));

        if (!$item) {
            return $this->respond($request, false, 'That lead is no longer pending.');
        }

        $this->approver->dismiss($owner, $item, $request->user()?->id);

        return $this->respond($request, true, 'Lead dismissed.');
    }

    /**
     * Dry-run a bulk approve: report how many of the selected leads can
     * actually be approved under the plan's contact cap (and how many would
     * be blocked) so the creator can decide before committing. Nothing is
     * persisted here.
     */
    public function bulkPreview(Request $request)
    {
        $v = $request->validate([
            'items'  => 'required|array|min:1|max:200',
            'items.*.source_type' => 'required|string',
            'items.*.source_id'   => 'required|integer',
        ]);

        $owner = $this->owner($request);
        $plan = $this->approver->planBatch($owner, $v['items']);

        return response()->json(['success' => true] + $plan);
    }

    /** Bulk approve/dismiss a batch of {source_type, source_id} pairs. */
    public function bulk(Request $request)
    {
        $v = $request->validate([
            'action' => 'required|in:approve,dismiss',
            'items'  => 'required|array|min:1|max:200',
            'items.*.source_type' => 'required|string',
            'items.*.source_id'   => 'required|integer',
        ]);

        $owner = $this->owner($request);
        $aggregator = new LeadAggregator($owner->id);

        // Separate outcomes so the creator sees exactly what happened rather
        // than one opaque "failed" bucket: new contacts created, existing
        // contacts merged into, leads blocked by the plan's contact cap, and
        // leads that are no longer pending (handled elsewhere in the meantime).
        $created = 0; $merged = 0; $blocked = 0; $gone = 0; $ok = 0; $capMessage = null;

        foreach ($v['items'] as $row) {
            $item = $aggregator->find($row['source_type'], (int) $row['source_id']);
            if (!$item) { $gone++; continue; }

            if ($v['action'] === 'approve') {
                try {
                    $result = $this->approver->approve($owner, $item, $request->user()?->id);
                    if (($result['result'] ?? null) === LeadApprover::RESULT_MERGED) {
                        $merged++;
                    } else {
                        $created++;
                    }
                } catch (\RuntimeException $e) {
                    $blocked++;
                    $capMessage = $e->getMessage();
                }
            } else {
                $this->approver->dismiss($owner, $item, $request->user()?->id);
                $ok++;
            }
        }

        if ($v['action'] === 'approve') {
            $approved = $created + $merged;
            $parts = [];
            if ($created) $parts[] = "{$created} new";
            if ($merged)  $parts[] = "{$merged} merged";
            $message = "Approved {$approved} lead(s)" . ($parts ? ' (' . implode(', ', $parts) . ')' : '') . '.';
            if ($blocked) {
                $message .= " {$blocked} couldn't be approved — " . ($capMessage ?: "you've reached your plan's contact limit.");
            }
            if ($gone) {
                $message .= " {$gone} were no longer pending.";
            }
            $success = $blocked === 0;
        } else {
            $message = "Dismissed {$ok} lead(s)" . ($gone ? ", {$gone} were no longer pending." : '.');
            $success = true;
        }

        return $this->respond($request, $success, $message, [
            'created' => $created,
            'merged'  => $merged,
            'blocked' => $blocked,
            'gone'    => $gone,
        ]);
    }

    protected function owner(Request $request)
    {
        return workspace_owner() ?? $request->user();
    }

    protected function respond(Request $request, bool $success, string $message, array $extra = [])
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json(array_merge(['success' => $success, 'message' => $message], $extra), $success ? 200 : 422);
        }

        return back()->with($success ? 'success' : 'error', $message);
    }
}
