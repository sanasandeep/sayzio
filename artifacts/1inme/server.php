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
