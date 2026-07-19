<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ZioBrowserDownloadFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('zio_browser_release_v1');
        Cache::forget('app_setting:zio_browser_last_release');
    }

    private function fakeGithubRelease(string $version): void
    {
        $tag = 'zio-browser-v' . $version;
        $base = "https://github.com/sanasandeep/sayzio/releases/download/{$tag}";
        Http::fake([
            'api.github.com/*' => Http::response([[
                'tag_name' => $tag,
                'draft' => false,
                'prerelease' => false,
                'published_at' => '2026-07-01T00:00:00Z',
                'assets' => [
                    ['name' => "SayZio.Browser-{$version}-arm64.dmg", 'browser_download_url' => "{$base}/SayZio.Browser-{$version}-arm64.dmg"],
                    ['name' => "SayZio.Browser-{$version}.dmg", 'browser_download_url' => "{$base}/SayZio.Browser-{$version}.dmg"],
                    ['name' => "SayZio.Browser.Setup.{$version}.exe", 'browser_download_url' => "{$base}/SayZio.Browser.Setup.{$version}.exe"],
                ],
            ]], 200),
        ]);
    }

    public function test_successful_fetch_persists_last_good_release(): void
    {
        $this->fakeGithubRelease('0.4.2');

        $this->get('/download')->assertOk()->assertSee('0.4.2');

        $stored = AppSetting::where('key', 'zio_browser_last_release')->first();
        $this->assertNotNull($stored);
        $this->assertSame('0.4.2', $stored->value['version']);
        $this->assertStringContainsString('zio-browser-v0.4.2', $stored->value['windows_exe']);
    }

    public function test_outage_serves_persisted_release_not_hardcoded_pin(): void
    {
        AppSetting::put('zio_browser_last_release', [
            'version' => '0.9.9',
            'mac_arm64_dmg' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.9.9/SayZio.Browser-0.9.9-arm64.dmg',
            'mac_x64_dmg' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.9.9/SayZio.Browser-0.9.9.dmg',
            'windows_exe' => 'https://github.com/sanasandeep/sayzio/releases/download/zio-browser-v0.9.9/SayZio.Browser.Setup.0.9.9.exe',
        ]);
        Http::fake(['api.github.com/*' => Http::response(null, 500)]);

        $this->get('/download')
            ->assertOk()
            ->assertSee('0.9.9')
            ->assertDontSee('zio-browser-v0.1.0');
    }

    public function test_outage_with_nothing_persisted_uses_bootstrap_pin(): void
    {
        Http::fake(['api.github.com/*' => Http::response(null, 500)]);

        $this->get('/download')->assertOk()->assertSee('zio-browser-v0.1.0');
    }

    public function test_corrupt_persisted_value_falls_back_to_bootstrap_pin(): void
    {
        AppSetting::put('zio_browser_last_release', ['version' => '0.9.9']);
        Http::fake(['api.github.com/*' => Http::response(null, 500)]);

        $this->get('/download')->assertOk()->assertSee('zio-browser-v0.1.0');
    }
}
