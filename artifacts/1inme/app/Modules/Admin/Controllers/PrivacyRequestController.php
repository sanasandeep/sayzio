<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPrivacyDeletionJob;
use App\Jobs\ProcessPrivacyExportJob;
use App\Modules\Common\Models\PrivacyRequest;
use App\Modules\Common\Services\PrivacyRequestNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin "Privacy Requests" queue. Staff review verified GDPR/CCPA requests
 * and either approve them (which dispatches the matching fulfillment job —
 * deletion after a grace window, export immediately) or reject them with a
 * reason. Every decision is written to the request's audit trail.
 */
class PrivacyRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = PrivacyRequest::query()->latest();

        $status = $request->query('status');
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $type = $request->query('type');
        if (in_array($type, [PrivacyRequest::TYPE_DELETION, PrivacyRequest::TYPE_EXPORT], true)) {
            $query->where('type', $type);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('email', 'like', '%' . $search . '%');
        }

        $requests = $query->paginate(20)->withQueryString();

        $counts = PrivacyRequest::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.privacy-requests.index', [
            'requests' => $requests,
            'counts'   => $counts,
            'filters'  => [
                'status' => $status ?: 'all',
                'type'   => $type ?: 'all',
                'q'      => $search,
            ],
        ]);
    }

    public function show(PrivacyRequest $privacyRequest)
    {
        return view('admin.privacy-requests.show', [
            'pr' => $privacyRequest,
        ]);
    }

    public function approve(Request $request, PrivacyRequest $privacyRequest, PrivacyRequestNotifier $notifier)
    {
        // Only a verified request can be approved.
        if ($privacyRequest->status !== PrivacyRequest::STATUS_VERIFIED) {
            return back()->withErrors(['status' => 'Only verified requests can be approved.']);
        }

        $actor = Auth::guard('admin')->user();
        $actorTag = 'admin:' . ($actor->id ?? '?');

        if ($privacyRequest->isDeletion()) {
            // Deletion gets a cooling-off window; the scheduler dispatches
            // the job once scheduled_at is due. We also queue it now with a
            // delay as a primary path.
            $scheduledAt = now()->addDays(PrivacyRequest::DELETION_GRACE_DAYS);
            $privacyRequest->forceFill([
                'status'       => PrivacyRequest::STATUS_APPROVED,
                'approved_by'  => $actor->id ?? null,
                'approved_at'  => now(),
                'scheduled_at' => $scheduledAt,
            ])->save();
            $privacyRequest->recordAudit('approved', $actorTag, 'Deletion scheduled after grace window.');

            ProcessPrivacyDeletionJob::dispatch($privacyRequest->id)->delay($scheduledAt);
        } else {
            $privacyRequest->forceFill([
                'status'      => PrivacyRequest::STATUS_APPROVED,
                'approved_by' => $actor->id ?? null,
                'approved_at' => now(),
            ])->save();
            $privacyRequest->recordAudit('approved', $actorTag, 'Export queued for immediate generation.');

            ProcessPrivacyExportJob::dispatch($privacyRequest->id);
        }

        $notifier->notifyApproved($privacyRequest);

        return back()->with('status', 'Request approved.');
    }

    public function reject(Request $request, PrivacyRequest $privacyRequest, PrivacyRequestNotifier $notifier)
    {
        $data = $request->validate([
            'rejection_reason' => 'required|string|max:2000',
        ]);

        if (!in_array($privacyRequest->status, [
            PrivacyRequest::STATUS_PENDING_VERIFICATION,
            PrivacyRequest::STATUS_VERIFIED,
        ], true)) {
            return back()->withErrors(['status' => 'This request can no longer be rejected.']);
        }

        $actor = Auth::guard('admin')->user();

        $privacyRequest->forceFill([
            'status'           => PrivacyRequest::STATUS_REJECTED,
            'rejection_reason' => $data['rejection_reason'],
            'rejected_at'      => now(),
        ])->save();
        $privacyRequest->recordAudit('rejected', 'admin:' . ($actor->id ?? '?'), $data['rejection_reason']);

        $notifier->notifyRejected($privacyRequest);

        return back()->with('status', 'Request rejected.');
    }
}
