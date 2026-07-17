<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Coverage for the /storage/{path} → CloudFront bridge route
 * ({@see routes/web.php, storage.cdn.fallback}).
 *
 * The route exists to handle legacy `/storage/...` URLs that were generated
 * when the `public` disk was local. Once the disk moved to S3, existing
 * stored paths (in users.avatar, links.cover_image, etc.) keep working via
 * this redirect bridge instead of a broken 404 / 500.
 *
 * Key invariants:
 *   1. When the public disk is S3-backed and the disk resolves correctly,
 *      the route issues a 302 redirect to the CloudFront/S3 URL.
 *   2. When the public disk is NOT S3-backed (local driver), the route
 *      returns 404 so it doesn't interfere with the local symlink.
 *   3. When the S3 disk throws during URL resolution (misconfigured
 *      credentials, SDK init failure, etc.), the route returns 404 and
 *      logs a warning — it NEVER returns a 500 error page.
 *
 * The route was hardened to skip the `exists()` round-trip because:
 *   a) an S3 HeadObject on every avatar load is expensive, and
 *   b) the AWS SDK can throw during client construction even when the disk
 *      config declares `throw: false` — that flag only suppresses exceptions
 *      from actual API calls, not from SDK/credential initialization.
 *
 * No database is touched by this route, so no RefreshDatabase is needed.
 */
class StorageCdnFallbackTest extends TestCase
{
    private const TEST_PATH = 'avatars/Gm1SI5v9QUwKwKczoZNaSQVAFCbiPIRHd2aX843H.jpg';

    /**
     * S3-backed disk + successful URL resolution → 302 to the CDN URL.
     */
    public function test_s3_disk_redirects_to_cdn_url(): void
    {
        config(['filesystems.disks.public.driver' => 's3']);

        // Mock Storage so we control what url() returns without real AWS creds.
        $fakeDisk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $fakeDisk->shouldReceive('url')
            ->once()
            ->with(self::TEST_PATH)
            ->andReturn('https://cdn.example.com/' . self::TEST_PATH);

        Storage::shouldReceive('disk')
            ->with('public')
            ->once()
            ->andReturn($fakeDisk);

        $response = $this->get('/storage/' . self::TEST_PATH);

        $response->assertRedirect('https://cdn.example.com/' . self::TEST_PATH);
        $this->assertSame(302, $response->status());
    }

    /**
     * Non-S3 (local) disk → 404 immediately; the local symlink handles it.
     */
    public function test_local_disk_returns_404(): void
    {
        config(['filesystems.disks.public.driver' => 'local']);

        $response = $this->get('/storage/' . self::TEST_PATH);

        $response->assertNotFound();
    }

    /**
     * S3 disk that throws during URL resolution → 404, never 500.
     *
     * This is the production failure mode: the AWS SDK can throw during client
     * construction when credentials or bucket/region are missing, even though
     * the disk config has `throw: false`.
     */
    public function test_s3_exception_returns_404_not_500(): void
    {
        config(['filesystems.disks.public.driver' => 's3']);

        Storage::shouldReceive('disk')
            ->with('public')
            ->once()
            ->andThrow(new \RuntimeException('S3 credentials missing or bucket not found'));

        $response = $this->get('/storage/' . self::TEST_PATH);

        // Must be 404, never 500.
        $response->assertNotFound();
    }
}
