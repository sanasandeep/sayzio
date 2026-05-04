<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\GeoIpService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * "Devices & sessions" endpoints (task #1111).
 *
 * Lists all live sign-ins for the current user — both Sanctum tokens
 * (mobile app + API clients) and Laravel web sessions (when the
 * database session driver is in use) — and lets the user revoke any
 * single session or sign out everywhere except the current request.
 */
class SessionsController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $user    = $request->user();
        $current = $request->user()?->currentAccessToken();
        $currentTokenId = $current && method_exists($current, 'getKey') ? $current->getKey() : null;

        $items = [];

        // ── Sanctum tokens ────────────────────────────────────────
        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->getKey())
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
                'first_seen_at'  => optional($t->created_at)->toIso8601String(),
                'last_active_at' => optional($t->last_used_at ?? $t->created_at)->toIso8601String(),
                'is_current'     => $currentTokenId !== null && (int) $t->getKey() === (int) $currentTokenId,
            ];
        }

        // ── Web (database) sessions ───────────────────────────────
        if (config('session.driver') === 'database') {
            $sessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->orderByDesc('last_activity')
                ->get();

            $currentWebId = $request->hasSession() ? $request->session()->getId() : null;

            foreach ($sessions as $s) {
                $ua = (string) ($s->user_agent ?? '');
                $items[] = [
                    'id'             => 'session:'.$s->id,
                    'kind'           => 'web',
                    'client_kind'    => 'web',
                    'device_label'   => $this->labelFromUa($ua),
                    'platform'       => $this->platformFromUa($ua),
                    'user_agent'     => $ua ?: null,
                    'ip'             => $s->ip_address,
                    'country'        => $this->safeCountry($s->ip_address),
                    'first_seen_at'  => null,
                    'last_active_at' => $s->last_activity ? date('c', (int) $s->last_activity) : null,
                    'is_current'     => $currentWebId !== null && $s->id === $currentWebId,
                ];
            }
        }

        return $this->ok(['items' => $items]);
    }

    public function destroy(Request $request, string $id)
    {
        $user = $request->user();

        if (str_starts_with($id, 'token:')) {
            $tokenId = (int) substr($id, 6);
            $token   = PersonalAccessToken::query()
                ->where('tokenable_type', $user::class)
                ->where('tokenable_id', $user->getKey())
                ->where('id', $tokenId)
                ->first();
            if (!$token) return $this->notFound('Session not found');
            $token->delete();
            return $this->noContent();
        }

        if (str_starts_with($id, 'session:')) {
            if (config('session.driver') !== 'database') {
                return $this->fail('Web sessions are not stored on this server', 400, 'web_sessions_unavailable');
            }
            $sid = substr($id, 8);
            $deleted = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->where('id', $sid)
                ->delete();
            return $deleted ? $this->noContent() : $this->notFound('Session not found');
        }

        return $this->fail('Unknown session id', 400, 'invalid_session_id');
    }

    public function destroyOthers(Request $request)
    {
        $user    = $request->user();
        $current = $request->user()?->currentAccessToken();
        $currentTokenId = $current && method_exists($current, 'getKey') ? (int) $current->getKey() : null;

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', $user::class)
            ->where('tokenable_id', $user->getKey());
        if ($currentTokenId) {
            $tokens->where('id', '!=', $currentTokenId);
        }
        $revokedTokens = $tokens->delete();

        $revokedSessions = 0;
        if (config('session.driver') === 'database') {
            $q = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->getKey());
            if ($request->hasSession()) {
                $q->where('id', '!=', $request->session()->getId());
            }
            $revokedSessions = $q->delete();
        }

        return $this->ok([
            'revoked_tokens'   => $revokedTokens,
            'revoked_sessions' => $revokedSessions,
        ]);
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

    private function platformFromUa(string $ua): ?string
    {
        if (preg_match('/iPhone|iPad|iPod/i', $ua)) return 'ios';
        if (preg_match('/Android/i', $ua))          return 'android';
        if (preg_match('/Mac OS X/i', $ua))         return 'macos';
        if (preg_match('/Windows/i', $ua))          return 'windows';
        if (preg_match('/Linux/i', $ua))            return 'linux';
        return null;
    }

    private function safeCountry(?string $ip): ?string
    {
        if (!$ip) return null;
        try {
            return app(GeoIpService::class)->detectCountry($ip);
        } catch (\Throwable) {
            return null;
        }
    }
}
