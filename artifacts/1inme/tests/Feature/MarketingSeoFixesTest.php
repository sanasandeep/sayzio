<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\AppSetting;
use App\Modules\Common\Middleware\ProdStartupProbe;
use App\Modules\Common\Support\MarketingSchema;
use App\Modules\Common\Support\MarketingSeo;
use App\Modules\Common\Support\MarketingSitemap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class MarketingSeoFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_canonical_and_schema_ignore_arbitrary_query_parameters(): void
    {
        $response = $this->get('/?seo-audit=example&utm_source=test')->assertOk();
        $response->assertSee('<link rel="canonical" href="http://localhost/">', false);
        $response->assertDontSee('http://localhost/?seo-audit', false);
        $response->assertSee('Business Pages, CRM &amp; AI Marketing Tools', false);
        $response->assertSee('manage customers, sales and projects');
    }

    public function test_schema_uses_public_branding_instead_of_an_expiring_share_url(): void
    {
        AppSetting::put('marketing_default_share_image', 'https://bucket.s3.amazonaws.com/private.jpg?X-Amz-Signature=expired');
        $node = MarketingSchema::organization();
        $this->assertSame(url('/branding/icon.png'), $node['logo']['url']);
        AppSetting::put('brand_icon_url', '/branding/custom-icon.webp');
        $this->assertSame(url('/branding/custom-icon.webp'), MarketingSchema::organization()['logo']['url']);
        AppSetting::put('brand_icon_url', 'https://bucket.s3.amazonaws.com/logo.png?X-Amz-Signature=expired');
        $this->assertSame(url('/branding/icon.png'), MarketingSchema::organization()['logo']['url']);
    }

    public function test_source_revision_and_later_editor_changes_drive_home_sitemap_date(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20));
        AppSetting::put('marketing_home_code_updated_at', '2026-10-18T08:00:00+00:00');
        $this->assertSame('2026-10-18', MarketingSitemap::homeLastmod()->toDateString());
        AppSetting::put('marketing_hero_copy', ['headline' => 'New home content']);
        $this->assertSame('2026-10-20', MarketingSitemap::homeLastmod()->toDateString());
        $doc = new \DOMDocument;
        $doc->loadXML(MarketingSitemap::build());
        $xp = new \DOMXPath($doc);
        $xp->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $this->assertSame(1, $xp->query('//s:url[contains(s:loc,"localhost") or contains(s:loc,"sayzio.app")][1]/s:lastmod')->length);
        $this->travelBack();
    }

    public function test_production_public_clients_receive_the_homepage_instead_of_ok(): void
    {
        $this->app->instance('env', 'production');
        config(['app.root_startup_probe' => false]);
        foreach (['curl/8.7.1', 'python-requests/2.0', 'Googlebot', 'Go-http-client/1.1', 'Mozilla/5.0', ''] as $ua) {
            $request = Request::create('/', 'GET', [], [], [], ['HTTP_USER_AGENT' => $ua]);
            $response = (new ProdStartupProbe)->handle($request, fn () => response('<html><h1>Homepage</h1></html>'));
            $this->assertSame('<html><h1>Homepage</h1></html>', $response->getContent(), $ua);
        }
    }

    public function test_deploy_command_records_a_stable_source_revision(): void
    {
        $this->artisan('marketing:record-code-revision')->assertSuccessful();
        $date = AppSetting::get('marketing_home_code_updated_at');
        $this->assertNotEmpty($date);
        $this->artisan('marketing:record-code-revision')->assertSuccessful();
        $this->assertSame($date, AppSetting::get('marketing_home_code_updated_at'));
    }

    public function test_legacy_seo_upgrade_preserves_customized_copy(): void
    {
        $migration = require database_path('migrations/2026_10_16_010000_refresh_shipped_home_seo.php');
        AppSetting::put(MarketingSeo::SETTING_KEY, ['home' => ['title' => 'My custom homepage', 'description' => 'My description']]);
        $migration->up();
        $this->assertSame('My custom homepage', MarketingSeo::resolveCode('home')['title']);
        AppSetting::put(MarketingSeo::SETTING_KEY, ['home' => ['title' => 'Sayzio – All-in-One Link in Bio, QR Codes & Short Links', 'description' => 'Sayzio is the AI-powered digital presence platform: build your Link in Bio, short links and QR codes, then let Zio answer visitors and capture leads.']]);
        $migration->up();
        $this->assertSame(MarketingSeo::codeDrivenDefaults()['home']['title'], MarketingSeo::resolveCode('home')['title']);
        $this->assertSame(MarketingSeo::codeDrivenDefaults()['home']['description'],MarketingSeo::resolveCode('home')['description']);
    }
}
