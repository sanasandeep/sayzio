<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\SocialAccountConnection;
use App\Modules\User\Services\SocialFollowers\FollowerFetcherRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Connected accounts" — the per-user settings area where users link
 * the social accounts whose follower counts the public renderer pulls.
 *
 * Handle-only platforms (YouTube, Twitch, GitHub) only need a username/handle.
 * OAuth platforms (Instagram, TikTok, X, Facebook, LinkedIn, Pinterest)
 * additionally accept a long-lived access token pasted by the user — full
 * provider-side OAuth dances are out of scope for this iteration but the
 * data model, scheduler, and renderer are all wired up to use the token
 * the moment it is provided.
 */
class SocialAccountController extends Controller
{
    public function index()
    {
        $connections = SocialAccountConnection::where('user_id', Auth::id())
            ->orderBy('platform')->orderBy('handle')->get()->groupBy('platform');

        return view('user.social-accounts.index', [
            'connections' => $connections,
            'platforms'   => SocialAccountConnection::PLATFORM_META,
        ]);
    }

    public function store(Request $request, FollowerFetcherRegistry $registry)
    {
        $data = $request->validate([
            'platform'      => 'required|string|in:' . implode(',', array_keys(SocialAccountConnection::PLATFORM_META)),
            'handle'        => 'required|string|max:191',
            'access_token'  => 'nullable|string|max:4096',
            'refresh_token' => 'nullable|string|max:4096',
        ]);

        $handle = ltrim(trim($data['handle']), '@');

        $c = SocialAccountConnection::updateOrCreate(
            [
                'user_id'  => Auth::id(),
                'platform' => $data['platform'],
                'handle'   => $handle,
            ],
            [
                'access_token'             => $data['access_token'] ?: null,
                'refresh_token'            => $data['refresh_token'] ?: null,
                'last_refresh_status'      => 'pending',
                'last_refresh_error'       => null,
                // Manual (re)connect — clear any prior backoff so the new
                // credentials get a fresh start on the scheduler.
                'consecutive_failures'     => 0,
                'last_failure_notified_at' => null,
            ]
        );

        // Try one immediate refresh so the user sees a count right away.
        $registry->refresh($c);

        return redirect()->route('user.social-accounts.index')
            ->with('success', SocialAccountConnection::platformLabel($data['platform']) . ' account connected.');
    }

    public function refresh(SocialAccountConnection $connection, FollowerFetcherRegistry $registry)
    {
        abort_unless($connection->user_id === Auth::id(), 403);
        // A user-initiated retry should clear any backoff state so the next
        // scheduled cycle treats this connection as fresh again.
        $connection->forceFill([
            'consecutive_failures'     => 0,
            'last_failure_notified_at' => null,
        ])->save();
        $status = $registry->refresh($connection);

        $msg = match ($status) {
            'ok'          => 'Follower count refreshed.',
            'unsupported' => 'Refresh not supported for this platform yet.',
            default       => 'Refresh failed: ' . ($connection->last_refresh_error ?: 'unknown error'),
        };

        return redirect()->route('user.social-accounts.index')
            ->with($status === 'ok' ? 'success' : 'error', $msg);
    }

    public function destroy(SocialAccountConnection $connection)
    {
        abort_unless($connection->user_id === Auth::id(), 403);
        $platform = SocialAccountConnection::platformLabel($connection->platform);
        $connection->delete();

        return redirect()->route('user.social-accounts.index')
            ->with('success', "{$platform} account disconnected.");
    }
}
