<?php

namespace App\Modules\User\Controllers;

use App\Modules\Admin\Models\AccountBadge;
use App\Modules\User\Models\BadgeRequest;
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
}
