<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression guard for the dev-only unstyled-page fast-path in
 * App\Modules\Common\Middleware\DevStartupProbe.
 *
 * The middleware serves an instant "Starting Sayzio…" splash instead of
 * letting ANY HTML page render while the compiled Vite/Tailwind assets are not
 * yet ready on disk (missing public/build/manifest.json, malformed JSON, or a
 * manifest entry pointing at a file that does not exist). Without the asset
 * gate a browser navigation during a cold start / watch-cycle rebuild gap gets
 * a fully unstyled page (stylesheet 404, all responsive variants at once).
 *
 * There is no automated coverage of this branch, so a future edit to the
 * middleware — or a change to the manifest shape — could silently reintroduce
 * the unstyled render. These tests pin the behaviour by driving a real,
 * NON-root HTML route (/admin/login) with the on-disk asset state manipulated:
 *
 *   - manifest removed        => splash returned (not the real page)
 *   - referenced asset missing => splash returned (dangling reference)
 *   - everything present      => the real page renders normally
 *   - JSON / XHR / /build/*   => never intercepted, regardless of readiness
 *
 * The middleware only runs in the local/development environment (it is a
 * dev-only guard and NEVER runs in production), so each test forces the app
 * environment to 'local' before issuing the request. The real build artifacts
 * on disk are temporarily renamed and always restored (finally + tearDown
 * safety net) so a failing assertion can never leave the dev server without
 * its manifest.
 */
class DevStartupProbeAssetReadinessTest extends TestCase
{
    private const SPLASH_MARKER = 'Starting Sayzio…';

    /** Absolute paths renamed away during a test, to be restored on teardown. */
    private array $renamed = [];

    protected function setUp(): void
    {
        parent::setUp();

        // The guard is dev-only; the test env is "testing", so force it.
        $this->app['env'] = 'local';
    }

    protected function tearDown(): void
    {
        // Safety net: restore anything a failed assertion left renamed before
        // the finally block ran, so we never strand the real dev manifest.
        $this->restoreRenamed();

        parent::tearDown();
    }

    public function test_non_root_html_route_returns_splash_when_manifest_missing(): void
    {
        $manifest = public_path('build/manifest.json');
        $this->assertFileExists($manifest, 'Precondition: build manifest must exist to be removed.');

        try {
            $this->hideFile($manifest);

            $response = $this->get('/admin/login', ['Accept' => 'text/html']);

            $response->assertOk();
            $response->assertSee(self::SPLASH_MARKER, false);
        } finally {
            $this->restoreRenamed();
        }
    }

    public function test_non_root_html_route_returns_splash_when_referenced_asset_missing(): void
    {
        $manifest = public_path('build/manifest.json');
        $asset = $this->firstManifestAssetPath($manifest);
        $this->assertNotNull($asset, 'Precondition: manifest must reference at least one file.');
        $this->assertFileExists($asset);

        try {
            // Manifest stays intact; a referenced file is gone => dangling
            // reference => assetsReady() must fail safe toward the splash.
            $this->hideFile($asset);

            $response = $this->get('/admin/login', ['Accept' => 'text/html']);

            $response->assertOk();
            $response->assertSee(self::SPLASH_MARKER, false);
        } finally {
            $this->restoreRenamed();
        }
    }

    public function test_non_root_html_route_renders_real_page_when_assets_ready(): void
    {
        // No file manipulation: the real, compiled manifest + assets are
        // present, so the guard must step aside and render the real page.
        $manifest = public_path('build/manifest.json');
        $this->assertFileExists($manifest, 'Precondition: compiled assets must be built for this test.');

        $response = $this->get('/admin/login', ['Accept' => 'text/html']);

        $response->assertOk();
        $response->assertDontSee(self::SPLASH_MARKER, false);
        // The real admin login view references the compiled entrypoints via
        // @vite, proving the manifest was resolved rather than short-circuited.
        $response->assertSee('build/assets/', false);
    }

    public function test_json_and_build_requests_are_never_intercepted_even_when_assets_not_ready(): void
    {
        // Break asset readiness (manifest present, referenced file gone) so the
        // HTML gate WOULD fire — then prove the exempt request shapes still
        // bypass the splash entirely.
        $manifest = public_path('build/manifest.json');
        $asset = $this->firstManifestAssetPath($manifest);
        $this->assertNotNull($asset);

        try {
            $this->hideFile($asset);

            // Sanity: the gate is active for a plain HTML navigation.
            $this->get('/admin/login', ['Accept' => 'text/html'])
                ->assertSee(self::SPLASH_MARKER, false);

            // JSON navigation to the same route: wantsJson() => not intercepted.
            $this->getJson('/admin/login')
                ->assertDontSee(self::SPLASH_MARKER, false);

            // API path (api/*) is exempt by PATH, independent of the Accept
            // header — even a browser-style Accept: text/html must pass through.
            $this->get('/api/v1/__probe_nonexistent__', ['Accept' => 'text/html'])
                ->assertDontSee(self::SPLASH_MARKER, false);

            // XHR navigation: ajax() => not intercepted.
            $this->get('/admin/login', [
                'Accept' => 'text/html',
                'X-Requested-With' => 'XMLHttpRequest',
            ])->assertDontSee(self::SPLASH_MARKER, false);

            // Compiled-asset request (/build/*): never intercepted, so the
            // guard can never block the very stylesheet it is waiting on.
            $this->get('/build/manifest.json', ['Accept' => 'text/html'])
                ->assertDontSee(self::SPLASH_MARKER, false);
        } finally {
            $this->restoreRenamed();
        }
    }

    /** Resolve the on-disk path of the first file referenced by the manifest. */
    private function firstManifestAssetPath(string $manifestPath): ?string
    {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (!is_array($manifest)) {
            return null;
        }

        foreach ($manifest as $entry) {
            if (is_array($entry) && isset($entry['file'])) {
                return public_path('build/' . $entry['file']);
            }
        }

        return null;
    }

    /** Rename a file out of the way, remembering it for restoration. */
    private function hideFile(string $path): void
    {
        $hidden = $path . '.probe-test-bak';
        rename($path, $hidden);
        $this->renamed[$path] = $hidden;
    }

    private function restoreRenamed(): void
    {
        foreach ($this->renamed as $original => $hidden) {
            if (is_file($hidden)) {
                rename($hidden, $original);
            }
        }
        $this->renamed = [];
    }
}
