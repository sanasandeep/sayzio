<?php

namespace App\Modules\Common\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dev-only startup fast-path for the root "/" route.
 *
 * The Replit dev workflow readiness probe is hard-wired to poll the preview
 * path "/" (it ignores [services.development.health.startup].path) and enforces
 * a short per-response latency bound: it rejects the home page's ~1s warm /
 * ~3-5s cold Blade render and tears the (otherwise healthy) server down.
 *
 * During a brief window after boot we answer "/" with an instant lightweight
 * 200 (an auto-refreshing splash) so the probe immediately sees a healthy
 * endpoint; once the window elapses the real home page is served normally.
 * The boot timestamp is written by the dev run command
 * (.replit-artifact/artifact.toml) into storage/framework/cache/dev_boot_ms.
 *
 * This NEVER runs in production (guarded by the environment check) and only
 * ever affects a GET "/" during the post-boot window. It is prepended to the
 * global stack (runs before StartSession), so it must not depend on session or
 * auth state — it intentionally treats every "/" hit in the window the same.
 */
class DevStartupProbe
{
    /** How long after boot to serve the splash instead of the heavy home page. */
    private const WINDOW_MS = 20000;

    private const MARKER = 'framework/cache/dev_boot_ms';

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->withinStartupWindow($request)) {
            return $this->splash();
        }

        return $next($request);
    }

    private function withinStartupWindow(Request $request): bool
    {
        if (!app()->environment(['local', 'development'])) {
            return false;
        }

        if ($request->getMethod() !== 'GET' || !$request->is('/')) {
            return false;
        }

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
<title>Starting 1INME…</title>
<style>
  html,body{height:100%;margin:0}
  body{display:grid;place-items:center;background:#0b0b14;color:#e9e7ff;
       font-family:"Space Grotesk",system-ui,-apple-system,sans-serif}
  .box{text-align:center;max-width:340px;padding:24px}
  .dot{width:42px;height:42px;margin:0 auto 18px;border-radius:50%;
       border:3px solid rgba(167,139,250,.25);border-top-color:#a78bfa;
       animation:spin .8s linear infinite}
  @keyframes spin{to{transform:rotate(360deg)}}
  strong{font-size:17px;letter-spacing:.3px}
  p{opacity:.65;font-size:13px;line-height:1.5;margin:.6em 0 0}
</style>
</head>
<body>
  <div class="box">
    <div class="dot"></div>
    <strong>Starting 1INME…</strong>
    <p>Warming up the dev server — this page refreshes automatically.</p>
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
