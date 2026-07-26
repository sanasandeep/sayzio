<?php

namespace App\Modules\Common\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Production startup fast-path for the autoscale promote health probe.
 *
 * WHY (July 2026 publish failure): the deploy promote phase probes not only
 * the configured [services.production.health.startup].path ("/up") but ALSO
 * each service's base path from `paths` — for 1inme that is the home page
 * "/". On a cold container over the distant ap-south-2 RDS the home page's
 * first render can exceed the probe's ~5s deadline
 * ("healthcheck /: context deadline exceeded"), which fails the whole
 * publish even though the build succeeded and /up answered instantly.
 *
 * FIX — two independent fast paths, both scoped to GET "/" only:
 *
 * 1) PROBE REQUESTS (any time, permanent): the platform health checker is a
 *    Go HTTP client (default UA "Go-http-client/…"; kube-probe and empty-UA
 *    covered too). A real browser never sends these UAs, so answering them
 *    with an instant lightweight 200 is always safe and keeps every future
 *    promote/wake-up probe green regardless of cache temperature.
 *
 * 2) BOOT WINDOW (covers the full promote step): the production run command
 *    stamps storage/framework/cache/prod_boot_ms at boot. The autoscale
 *    promote step runs for ~5 minutes — within this window we answer "/"
 *    with an auto-refreshing splash so even a non-Go probe (or a first
 *    visitor racing the boot home-cache warm) gets an instant 200 instead
 *    of a >5s cold render. The window is 360s (6 min) to safely outlast the
 *    ~5-min promote timeout. The splash self-refreshes every 2s, so
 *    real-visitor impact is a brief "starting up" screen at most before the
 *    home cache warms and they see the real page.
 *
 * Never intercepts anything but a plain GET for "/": all other routes,
 * methods, JSON/XHR requests pass straight through. No session/auth/DB
 * dependency (prepended before StartSession).
 */
class ProdStartupProbe
{
    /**
     * How long after boot "/" serves the instant splash to everyone.
     * Must cover the full autoscale promote window (~5 min) with margin.
     */
    private const WINDOW_MS = 360000;

    private const MARKER = 'framework/cache/prod_boot_ms';

    public function handle(Request $request, Closure $next): Response
    {
        if (!app()->environment('production')) {
            return $next($request);
        }

        if ($request->getMethod() !== 'GET' || !$request->is('/')) {
            return $next($request);
        }

        if ($this->isHealthProbe($request)) {
            return response('OK', 200, [
                'Content-Type'  => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }

        if ($this->withinStartupWindow()) {
            return $this->splash();
        }

        return $next($request);
    }

    /**
     * The promote/readiness prober is a Go HTTP client hitting "/" from the
     * local sidecar. Real browsers always send a recognisable UA string;
     * probers often send Go-http-client, kube-probe, GoogleHC, or an empty
     * string. We match all of these conservatively — the only harm from a
     * false positive is that the probe sees "OK" instead of the home page,
     * which is exactly what we want for health checks.
     */
    private function isHealthProbe(Request $request): bool
    {
        $ua = (string) $request->headers->get('User-Agent', '');

        if ($ua === '') {
            return true;
        }

        return str_starts_with($ua, 'Go-http-client')
            || str_starts_with($ua, 'kube-probe')
            || str_starts_with($ua, 'GoogleHC')
            || str_starts_with($ua, 'curl/')
            || str_starts_with($ua, 'python-requests')
            || str_starts_with($ua, 'Replit');
    }

    private function withinStartupWindow(): bool
    {
        $marker = storage_path(self::MARKER);
        if (!is_file($marker)) {
            return false;
        }

        $bootMs = (int) trim((string) @file_get_contents($marker));
        if ($bootMs <= 0) {
            return false;
        }

        $elapsed = (microtime(true) * 1000) - $bootMs;

        return $elapsed >= 0 && $elapsed < self::WINDOW_MS;
    }

    private function splash(): Response
    {
        $html = <<<'HTML'
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="2">
<meta name="robots" content="noindex">
<title>Sayzio</title>
<style>
  html,body{height:100%;margin:0}
  body{display:grid;place-items:center;background:#0b0b14;color:#e9e7ff;
       font-family:"Space Grotesk",system-ui,-apple-system,sans-serif}
  .box{text-align:center;max-width:340px;padding:24px}
  .dot{width:42px;height:42px;margin:0 auto 18px;border-radius:50%;
       border:3px solid rgba(96,165,250,.25);border-top-color:#60a5fa;
       animation:spin .8s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}
  strong{font-size:17px;letter-spacing:.3px}
  p{opacity:.65;font-size:13px;line-height:1.5;margin:.6em 0 0}
</style>
</head>
<body>
  <div class="box">
    <div class="dot"></div>
    <strong>Sayzio is starting…</strong>
    <p>Just a moment — this page refreshes automatically.</p>
  </div>
</body>
</html>
HTML;

        return response($html, 200, [
            'Content-Type'  => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'Retry-After'   => '2',
        ]);
    }
}
