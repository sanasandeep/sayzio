<?php

namespace App\Modules\User\Controllers;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\Admin\Services\UserAccountNotifier;
use App\Modules\User\Models\BadgeRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Services\BadgeRequestService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

/**
 * User-facing self-serve badge requests (Task #2910). Users list their
 * own requests, pick an existing badge or describe a custom one, and see
 * the outcome. Admins review submissions from the separate admin queue.
 */
class BadgeRequestController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $requests = BadgeRequest::where('user_id', $user->id)
            ->with(['badge', 'assignedBadge'])
            ->orderByDesc('created_at')
            ->get();

        $badges   = AccountBadge::orderBy('name')->get();
        $ownedIds = $user->accountBadges()->pluck('account_badges.id')->all();

        return view('user.badge-requests.index', compact('requests', 'badges', 'ownedIds'));
    }

    public function store(Request $request, BadgeRequestService $service)
    {
        $data = $request->validate([
            'account_badge_id' => 'nullable|integer|exists:account_badges,id',
            'custom_name'      => 'nullable|string|max:120',
            'reason'           => 'required|string|max:2000',
        ]);

        $result = $service->submit(
            Auth::user(),
            $data['account_badge_id'] ?? null,
            $data['custom_name'] ?? null,
            $data['reason'],
        );

        if (! $result['ok']) {
            return back()->withInput()->with('error', $result['message']);
        }

        return redirect()->route('user.badge-requests.index')
            ->with('success', $result['message']);
    }

    /**
     * Live handle → account lookup backing the "Give a badge" form's instant
     * feedback (Task #3045). Mirrors the discovery-by-handle resolution
     * (case-insensitive) so the verdict shown matches what give() enforces on
     * submit. Returns a plain JSON verdict, never the recipient's private data.
     */
    public function lookupHandle(Request $request)
    {
        $handle = ltrim(trim((string) $request->query('handle', '')), '@');

        if ($handle === '') {
            return response()->json(['found' => null, 'message' => 'Enter a handle to find someone.']);
        }

        $recipient = \App\Modules\User\Models\CreatorProfile::ownerUserForHandle($handle);
        if (! $recipient) {
            return response()->json(['found' => false, 'message' => "No account found with the handle \"@{$handle}\"."]);
        }

        if ($recipient->id === Auth::id()) {
            return response()->json(['found' => false, 'self' => true, 'message' => "That's you — you can't give a badge to yourself."]);
        }

        return response()->json([
            'found'   => true,
            'name'    => $recipient->name ?: ('User ' . $recipient->id),
            'handle'  => $recipient->handle,
            'message' => "Found @{$recipient->handle}.",
        ]);
    }

    /**
     * Creator hands a badge they personally hold to another account, found by
     * handle (Task #3045). The chosen badge MUST be one the authenticated
     * creator currently holds — this is re-verified server-side; the client's
     * filtered list is never trusted. Guards block self-assignment, unknown
     * handles and duplicates. The grant is stamped with the granting user so
     * it's auditable and distinct from admin/self-request assignments.
     */
    public function give(Request $request, UserAccountNotifier $notifier)
    {
        $giver = Auth::user();

        $data = $request->validate([
            'handle'           => 'required|string|max:120',
            'account_badge_id' => 'required|integer',
        ]);

        // Ownership re-check: only badges the creator currently holds are giftable.
        $badge = $giver->accountBadges()
            ->where('account_badges.id', $data['account_badge_id'])
            ->first();
        if (! $badge) {
            return back()->withInput()
                ->with('error', 'You can only give a badge you currently hold.');
        }

        // Resolve the recipient by handle (case-insensitive, leading @ tolerated).
        $handle = ltrim(trim($data['handle']), '@');
        $recipient = \App\Modules\User\Models\CreatorProfile::ownerUserForHandle($handle);
        if (! $recipient) {
            return back()->withInput()
                ->with('error', "No account found with the handle \"@{$handle}\".");
        }

        // Can't give to yourself.
        if ($recipient->id === $giver->id) {
            return back()->withInput()
                ->with('error', "You can't give a badge to yourself.");
        }

        // No-op when the recipient already has the badge.
        if ($recipient->accountBadges()->where('account_badges.id', $badge->id)->exists()) {
            $who = $recipient->name ?: ('@' . $recipient->handle);
            return back()->withInput()
                ->with('error', "{$who} already has the \"{$badge->name}\" badge.");
        }

        // Attach, stamping the granting creator for audit.
        $recipient->accountBadges()->syncWithoutDetaching([
            $badge->id => ['assigned_by' => $giver->id],
        ]);

        $granterName = $giver->name ?: ('@' . $giver->handle);
        $notifier->badgeGranted($recipient, $badge->name, $granterName);

        return back()->with('success', "Gave the \"{$badge->name}\" badge to @{$recipient->handle}.");
    }
}
