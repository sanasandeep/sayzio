<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Coverage for the retired /storage/{path} bridge, now a thin 404-logging
 * shim ({@see routes/web.php, storage.cdn.fallback}).
 *
 * Legacy `/storage/...` DB values were rewritten to canonical CDN URLs by
 * `storage:canonicalize-legacy-paths` (production dry-run confirmed 0 legacy
 * rows, July 2026), so the old redirect-to-CloudFront bridge is retired.
 * The route is deliberately KEPT as a shim so that:
 *
 *   1. Straggler requests (e.g. `/storage/...` URLs baked into old emails or
 *      exports) log a warning for follow-up instead of being silently
 *      swallowed by the `/{alias}` catch-all as a bogus link alias.
 *   2. It always 404s — no S3 SDK involvement, so it can never 500.
 *
 * The route itself touches no database rows, but rendering the 404 error
 * page pulls in the site layout (site_pages, site assistant hints, …), so
 * RefreshDatabase is required for the tests to run on a fresh/ephemeral DB.
 */
class StorageCdnFallbackTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_PATH = 'avatars/Gm1SI5v9QUwKwKczoZNaSQVAFCbiPIRHd2aX843H.jpg';

    /**
     * The shim always 404s — no redirect, regardless of disk driver.
     */
    public function test_shim_returns_404(): void
    {
        config(['filesystems.disks.public.driver' => 's3']);
        $this->get('/storage/' . self::TEST_PATH)->assertNotFound();

        config(['filesystems.disks.public.driver' => 'local']);
        $this->get('/storage/' . self::TEST_PATH)->assertNotFound();
    }

    /**
     * Straggler requests are logged so lingering legacy URLs are visible.
     */
    public function test_shim_logs_a_warning_with_the_requested_path(): void
    {
        Log::shouldReceive('warning')
            ->atLeast()->once()
            ->withArgs(function (string $message, array $context = []) {
                return str_contains($message, 'retired /storage bridge')
                    && ($context['path'] ?? null) === self::TEST_PATH;
            });
        // The 404 error page render may log unrelated lines at other levels.
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $this->get('/storage/' . self::TEST_PATH)->assertNotFound();
    }
}
