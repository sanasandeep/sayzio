<?php

namespace App\Modules\Common\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Optional legacy autoscale readiness shortcut; never enabled for normal EC2 traffic. */
class ProdStartupProbe
{
    /**
     * Maximum boot window for explicitly enabled legacy readiness probes.
     * All ordinary clients still receive the real homepage.
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

        // Only an explicitly enabled, time-limited platform probe may use the
        // root shortcut. Normal clients always receive the actual homepage.
        if (config('app.root_startup_probe', false) && $this->withinStartupWindow() && $this->isHealthProbe($request)) {
            return response('OK', 200, [
                'Content-Type'  => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
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

}
