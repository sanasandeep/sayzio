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

        $ok = 0; $failed = 0; $lastError = null;
        foreach ($v['items'] as $row) {
            $item = $aggregator->find($row['source_type'], (int) $row['source_id']);
            if (!$item) { $failed++; continue; }

            try {
                if ($v['action'] === 'approve') {
                    $this->approver->approve($owner, $item, $request->user()?->id);
                } else {
                    $this->approver->dismiss($owner, $item, $request->user()?->id);
                }
                $ok++;
            } catch (\RuntimeException $e) {
                $failed++;
                $lastError = $e->getMessage();
            }
        }

        $message = $v['action'] === 'approve'
            ? "Approved {$ok} lead(s)" . ($failed ? ", {$failed} failed" . ($lastError ? " ({$lastError})" : '') : '.')
            : "Dismissed {$ok} lead(s)" . ($failed ? ", {$failed} failed." : '.');

        return $this->respond($request, $failed === 0, $message);
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
