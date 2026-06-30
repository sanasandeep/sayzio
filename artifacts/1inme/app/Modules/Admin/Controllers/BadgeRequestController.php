<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AccountBadge;
use App\Modules\Admin\Services\UserAccountNotifier;
use App\Modules\User\Models\BadgeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin review queue for self-serve badge requests (Task #2910). Gated at
 * the route layer by the dedicated `badge_requests.review` permission so
 * it can be granted independently of the badge-definition CRUD. Approval
 * attaches the badge via the `account_badge_user` pivot and notifies the
 * user in-app + email (via {@see UserAccountNotifier}).
 */
class BadgeRequestController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', 'pending');

        $query = BadgeRequest::with(['user', 'badge', 'assignedBadge']);
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $requests = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $counts = [
            'pending'  => BadgeRequest::where('status', 'pending')->count(),
            'approved' => BadgeRequest::where('status', 'approved')->count(),
            'rejected' => BadgeRequest::where('status', 'rejected')->count(),
        ];

        return view('admin.badge-requests.index', compact('requests', 'status', 'counts'));
    }

    public function review(BadgeRequest $badgeRequest)
    {
        $badgeRequest->load(['user', 'badge', 'assignedBadge']);
        $badges = AccountBadge::orderBy('name')->get();

        return view('admin.badge-requests.review', compact('badgeRequest', 'badges'));
    }

    public function approve(Request $request, BadgeRequest $badgeRequest, UserAccountNotifier $notifier)
    {
        if (! $badgeRequest->isPending()) {
            return redirect()->route('admin.badge-requests.index')
                ->with('error', 'This request has already been ' . $badgeRequest->status . '.');
        }

        $data = $request->validate([
            'assign_badge_id' => 'nullable|integer|exists:account_badges,id',
            'new_badge_name'  => 'nullable|string|max:120',
            'new_badge_color' => 'nullable|string|max:9',
            'admin_notes'     => 'nullable|string|max:2000',
        ]);

        $badge = $this->resolveBadge($badgeRequest, $data);
        if (! $badge) {
            return back()->withInput()
                ->with('error', 'Pick an existing badge or create a new one to assign.');
        }

        // Attach the badge to the user (no-op if somehow already attached).
        $badgeRequest->user->accountBadges()->syncWithoutDetaching([$badge->id]);

        $badgeRequest->update([
            'status'            => 'approved',
            'assigned_badge_id' => $badge->id,
            'admin_notes'       => $data['admin_notes'] ?? null,
            'reviewed_at'       => now(),
            'reviewed_by'       => Auth::guard('admin')->id(),
        ]);

        $notifier->badgeApproved($badgeRequest->user, $badge->name);

        return redirect()->route('admin.badge-requests.index')
            ->with('success', 'Badge request approved and assigned.');
    }

    public function reject(Request $request, BadgeRequest $badgeRequest, UserAccountNotifier $notifier)
    {
        if (! $badgeRequest->isPending()) {
            return redirect()->route('admin.badge-requests.index')
                ->with('error', 'This request has already been ' . $badgeRequest->status . '.');
        }

        $data = $request->validate([
            'admin_notes' => 'required|string|max:2000',
        ]);

        $badgeRequest->update([
            'status'      => 'rejected',
            'admin_notes' => $data['admin_notes'],
            'reviewed_at' => now(),
            'reviewed_by' => Auth::guard('admin')->id(),
        ]);

        $notifier->badgeRejected($badgeRequest->user, $badgeRequest->requestedLabel(), $data['admin_notes']);

        return redirect()->route('admin.badge-requests.index')
            ->with('success', 'Badge request rejected.');
    }

    /**
     * Decide which badge to attach on approval. For an existing-badge
     * request we assign exactly what was asked for unless the admin
     * overrides; for a custom request the admin must pick an existing
     * badge or create a new one inline.
     */
    private function resolveBadge(BadgeRequest $badgeRequest, array $data): ?AccountBadge
    {
        if (! empty($data['assign_badge_id'])) {
            return AccountBadge::find($data['assign_badge_id']);
        }

        if ($badgeRequest->account_badge_id) {
            return $badgeRequest->badge;
        }

        if (! empty($data['new_badge_name'])) {
            $color = trim((string) ($data['new_badge_color'] ?? ''));

            return AccountBadge::create([
                'name'       => trim($data['new_badge_name']),
                'color'      => $color !== '' ? $color : AccountBadge::DEFAULT_COLOR,
                'created_by' => Auth::guard('admin')->id(),
            ]);
        }

        return null;
    }
}
