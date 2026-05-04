<?php

namespace App\Modules\Api\Middleware;

use App\Modules\Common\Services\GeoIpService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refreshes the metadata on the current Sanctum token after the request
 * has been authenticated. Sanctum already updates `last_used_at`; we
 * additionally stamp the most recent IP / UA / country so the Devices
 * & sessions page can show "last seen from <country>" accurately.
 *
 * Runs after `auth:sanctum`. No-op for guests, web sessions, or when
 * the metadata has not actually changed (so we don't write on every
 * single request).
 */
class TouchSessionToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $token = $request->user()?->currentAccessToken();
        } catch (\Throwable) {
            $token = null;
        }

        // Transient personal access tokens (no DB row) and web session
        // guards both surface as something other than the Sanctum
        // model — skip them.
        if (!$token || !method_exists($token, 'getKey')) {
            return $response;
        }

        $ip = (string) ($request->ip() ?? '');
        $ua = (string) ($request->userAgent() ?? '');

        $changes = [];
        if ($ip !== '' && $token->last_ip !== $ip) {
            $changes['last_ip'] = $ip;
            try {
                $cc = app(GeoIpService::class)->detectCountry($ip);
            } catch (\Throwable) {
                $cc = null;
            }
            $changes['last_country'] = $cc;
        }
        if ($ua !== '' && $token->last_user_agent !== $ua) {
            $changes['last_user_agent'] = mb_substr($ua, 0, 500);
        }

        if (!empty($changes)) {
            $token->forceFill($changes)->save();
        }

        return $response;
    }
}
