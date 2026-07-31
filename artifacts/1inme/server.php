<?php

/**
 * PHP built-in server router for PRODUCTION deploys.
 *
 * The production run command serves the app with PHP's built-in web server
 * (`php -S ... -t public server.php`) instead of `php artisan serve`, because
 * `artisan serve` spawns a child `php -S` that strips all environment variables
 * except a small passthrough allow-list (see
 * .agents/memory/artisan-serve-env-passthrough.md) — which would break DB_* and
 * other runtime config in production.
 *
 * But pointing `php -S` directly at `public/index.php` makes Laravel's front
 * controller the router for EVERY request, so static assets (/branding/*.png,
 * /images/*.png, /build/*.css, etc.) get swallowed by Laravel and returned as
 * HTML — breaking every image and stylesheet in production while dev (which uses
 * `artisan serve`'s own static-aware router) works fine.
 *
 * This router mirrors Laravel's `artisan serve` behaviour: real files under the
 * public/ document root are served as-is (return false lets the built-in server
 * stream them with the correct Content-Type), and everything else is handed to
 * the framework front controller.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

/*
 * INSTANT HEALTH-PROBE FAST PATH (pre-framework).
 *
 * WHY (July 2026 republish failures): the autoscale promote step probes "/"
 * (each service's base path) with a ~5s deadline. ProdStartupProbe middleware
 * already answers probes with a lightweight 200 — but it runs INSIDE Laravel,
 * and on a cold container the framework boot itself (autoload, providers,
 * boot-time app_settings reads over the cross-region RDS) can exceed the
 * deadline per worker before the middleware is ever reached
 * ("healthcheck /: context deadline exceeded" → publish rejected).
 *
 * This block runs before Laravel loads (microseconds, no DB, no autoload):
 *  - GET /up          → instant 200 OK (mirrors Laravel's built-in health
 *                       route, which is DB-free anyway).
 *  - GET / from a probe UA (Go-http-client / kube-probe / GoogleHC / curl /
 *    python-requests / Replit / empty) → instant 200 OK. Real browsers always
 *    send a recognisable UA, so humans never hit this branch.
 *  - GET / from anyone during the boot window (prod_boot_ms marker, first
 *    6 min) → instant auto-refreshing "starting up" splash, so a first
 *    visitor racing the cache warm gets a fast 200 instead of a cold render.
 *
 * Scope guards:
 *  - Production only (APP_ENV): dev uses `artisan serve` and never loads this
 *    router, but the env gate keeps behavior identical if it ever does.
 *  - "/up" is only short-circuited DURING the boot window; once the window
 *    closes, /up goes back to Laravel so it reflects real app health instead
 *    of masking a broken deploy with an unconditional 200.
 *
 * Mirrors App\Modules\Common\Middleware\ProdStartupProbe (kept as
 * defense-in-depth for warm-worker requests); keep UA list and window in sync.
 */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && ($uri === '/' || $uri === '/up')
    && (getenv('APP_ENV') ?: 'production') === 'production') {
    $withinBootWindow = false;
    $marker = __DIR__ . '/storage/framework/cache/prod_boot_ms';
    if (is_file($marker)) {
        $bootMs = (int) trim((string) @file_get_contents($marker));
        $elapsed = (microtime(true) * 1000) - $bootMs;
        $withinBootWindow = $bootMs > 0 && $elapsed >= 0 && $elapsed < 360000;
    }

    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $isProbe = $ua === ''
        || str_starts_with($ua, 'Go-http-client')
        || str_starts_with($ua, 'kube-probe')
        || str_starts_with($ua, 'GoogleHC')
        || str_starts_with($ua, 'curl/')
        || str_starts_with($ua, 'python-requests')
        || str_starts_with($ua, 'Replit');

    if (($uri === '/up' && $withinBootWindow) || ($uri === '/' && $isProbe)) {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        echo 'OK';
        return true;
    }

    // Non-probe "/" during the boot window: instant splash instead of a cold render.
    if ($uri === '/' && $withinBootWindow) {
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        header('Retry-After: 2');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta http-equiv="refresh" content="2"><meta name="robots" content="noindex">'
            . '<title>Sayzio</title><style>html,body{height:100%;margin:0}'
            . 'body{display:grid;place-items:center;background:#0b0b14;color:#e9e7ff;'
            . 'font-family:"Space Grotesk",system-ui,-apple-system,sans-serif}'
            . '.box{text-align:center;max-width:340px;padding:24px}'
            . '.dot{width:42px;height:42px;margin:0 auto 18px;border-radius:50%;'
            . 'border:3px solid rgba(96,165,250,.25);border-top-color:#60a5fa;'
            . 'animation:spin .8s linear infinite}@keyframes spin{to{transform:rotate(360deg)}}'
            . 'strong{font-size:17px;letter-spacing:.3px}'
            . 'p{opacity:.65;font-size:13px;line-height:1.5;margin:.6em 0 0}</style></head>'
            . '<body><div class="box"><div class="dot"></div>'
            . '<strong>Sayzio is starting&hellip;</strong>'
            . '<p>Just a moment &mdash; this page refreshes automatically.</p>'
            . '</div></body></html>';
        return true;
    }
}

$publicPath = realpath(__DIR__ . '/public');

// Serve an existing static file directly (never the "/" index — let Laravel render the home page).
// realpath + prefix check contains the lookup to public/ as defense-in-depth against traversal.
if ($publicPath !== false && $uri !== '/') {
    $requested = realpath($publicPath . $uri);
    if ($requested !== false
        && is_file($requested)
        && str_starts_with($requested, $publicPath . DIRECTORY_SEPARATOR)) {
        return false;
    }
}

require_once $publicPath . '/index.php';
