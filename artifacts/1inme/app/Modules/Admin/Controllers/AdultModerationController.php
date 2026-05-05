<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin moderation page for the 18+ flag (Task #1208).
 *
 * Lists every user who has currently enabled adult content (or whose
 * adult flag has been suspended by a previous moderation action) and
 * lets a moderator suspend or restore the public 18+ tag.
 *
 * Suspending the flag does NOT revoke the creator's age affirmation —
 * the audit fields (adult_content_enabled_at, age_verified_at) remain
 * intact so we keep the consent trail. It only flips the public
 * surfaces back to SFW until support clears the issue.
 */
class AdultModerationController extends Controller
{
    public function index(Request $request)
    {
        $tab = $request->query('tab', 'enabled');

        $base = User::query();
        if ($tab === 'suspended') {
            $base->whereNotNull('adult_flag_suspended_at');
        } else {
            $base->where('adult_content_enabled', true)
                 ->whereNull('adult_flag_suspended_at');
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $base->where(function ($w) use ($like) {
                $w->where('name', 'ilike', $like)
                  ->orWhere('handle', 'ilike', $like)
                  ->orWhere('email', 'ilike', $like);
            });
        }

        $users = $base->orderByDesc('adult_content_enabled_at')
            ->paginate(25)->withQueryString();

        // Counters for the header tabs.
        $counts = [
            'enabled'   => User::where('adult_content_enabled', true)->whereNull('adult_flag_suspended_at')->count(),
            'suspended' => User::whereNotNull('adult_flag_suspended_at')->count(),
        ];

        return view('admin.adult-moderation.index', compact('users', 'tab', 'q', 'counts'));
    }

    public function suspend(Request $request, User $user)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $user->adult_flag_suspended_at      = now();
        $user->adult_flag_suspended_reason  = $data['reason'];
        $user->adult_flag_suspended_by      = Auth::guard('admin')->id() ?? Auth::id();
        $user->save();
        return back()->with('success', '18+ flag suspended for @' . $user->handle . '.');
    }

    public function restore(Request $request, User $user)
    {
        $user->adult_flag_suspended_at      = null;
        $user->adult_flag_suspended_reason  = null;
        $user->adult_flag_suspended_by      = null;
        $user->save();
        return back()->with('success', '18+ flag restored for @' . $user->handle . '.');
    }
}
