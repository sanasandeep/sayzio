<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\GeoIpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Web "Devices & sessions" page (task #1111). Lists every live web
 * session and Sanctum bearer token for the current user and lets them
 * revoke any one of them, or sign out everywhere except the browser
 * tab they're using right now.
 */
class SessionManagerController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $items = $this->collectItems($user, $request);
        return view('user.settings.sessions', compact('items'));
    }

    public function destroy(Request $request, string $id)
    {
        $user = Auth::user();

        if (str_starts_with($id, 'session:')) {
            $sid = substr($id, 8);
            // Refuse to destroy the current session via this route — the
            // dedicated "logout" flow handles that, otherwise the user
            // would just bounce back to /login on the next request.
            if ($request->session()->getId() === $sid) {
                return back()->with('error', 'You cannot revoke your current browser session here. Use Sign out instead.');
            }
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', $user->id)
                    ->where('id', $sid)
                    ->delete();
            }
            return back()->with('success', 'Session revoked.');
        }

        if (str_starts_with($id, 'token:')) {
            $tokenId = (int) substr($id, 6);
            PersonalAccessToken::query()
                ->where('tokenable_type', $user::class)
                ->where('tokenable_id', $user->id)
                ->where('id', $tokenId)
                ->delete();
            return back()->with('success', 'Device signed out.');
        }

        return back()->with('error', 'Unknown session identifier.');
    }

    public function destroyOthers(Request $request)
    {
        $user = Auth::user();

        // Tokens — drop all bearer tokens (mobile/API). The web user
        // can mint a new one on next mobile sign-in.
        PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id)
            ->delete();

        // Web sessions — keep this browser's row, drop the rest.
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return back()->with('success', 'Signed out of every other device.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectItems($user, Request $request): array
    {
        $items = [];

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->id)
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        foreach ($tokens as $t) {
            $items[] = [
                'id'             => 'token:'.$t->id,
                'kind'           => 'token',
                'client_kind'    => $t->client_kind ?? 'mobile',
                'device_label'   => $t->device_label ?: ($t->name ?: 'API client'),
                'platform'       => $t->platform,
                'user_agent'     => $t->last_user_agent ?: $t->created_user_agent,
                'ip'             => $t->last_ip ?: $t->created_ip,
                'country'        => $t->last_country ?: $t->created_country,
                'first_seen_at'  => $t->created_at,
                'last_active_at' => $t->last_used_at ?? $t->created_at,
                'is_current'     => false,
            ];
        }

        if (config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get();
            $currentSid = $request->session()->getId();
            $geo = app(GeoIpService::class);
            foreach ($sessions as $s) {
                $ua = (string) ($s->user_agent ?? '');
                $items[] = [
                    'id'             => 'session:'.$s->id,
                    'kind'           => 'web',
                    'client_kind'    => 'web',
                    'device_label'   => $this->labelFromUa($ua),
                    'platform'       => null,
                    'user_agent'     => $ua,
                    'ip'             => $s->ip_address,
                    'country'        => $this->safeCountry($geo, $s->ip_address),
                    'first_seen_at'  => null,
                    'last_active_at' => $s->last_activity ? \Carbon\Carbon::createFromTimestamp((int) $s->last_activity) : null,
                    'is_current'     => $s->id === $currentSid,
                ];
            }
        }

        // Sort current session first, then by last_active_at desc.
        usort($items, function ($a, $b) {
            if ($a['is_current'] !== $b['is_current']) {
                return $a['is_current'] ? -1 : 1;
            }
            $ta = $a['last_active_at'] ? (is_string($a['last_active_at']) ? strtotime($a['last_active_at']) : $a['last_active_at']->getTimestamp()) : 0;
            $tb = $b['last_active_at'] ? (is_string($b['last_active_at']) ? strtotime($b['last_active_at']) : $b['last_active_at']->getTimestamp()) : 0;
            return $tb <=> $ta;
        });

        return $items;
    }

    private function labelFromUa(string $ua): string
    {
        if ($ua === '') return 'Web browser';
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) return 'iPhone / iPad (web)';
        if (preg_match('/Android/i', $ua))          return 'Android (web)';
        if (preg_match('/Mac OS X/i', $ua))         return 'Mac (web)';
        if (preg_match('/Windows/i', $ua))          return 'Windows (web)';
        if (preg_match('/Linux/i', $ua))            return 'Linux (web)';
        return 'Web browser';
    }

    private function safeCountry(GeoIpService $geo, ?string $ip): ?string
    {
        if (!$ip) return null;
        try {
            return $geo->detectCountry($ip);
        } catch (\Throwable) {
            return null;
        }
    }
}
