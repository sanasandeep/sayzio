<?php

namespace Tests\Unit\Support;

use App\Modules\Common\Support\MarketingPageStandards as Standards;
use App\Modules\Common\Support\MarketingSeo;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class MarketingPageStandardsTest extends TestCase
{
    private $oldConfig;

    protected function setUp(): void
    {
        $app = Container::getInstance();
        $this->oldConfig = $app->bound('config') ? $app->make('config') : null;
        $app->instance('config', new Repository(['app' => ['name' => 'Sayzio']]));
    }

    protected function tearDown(): void
    {
        if ($this->oldConfig) Container::getInstance()->instance('config', $this->oldConfig);
        else Container::getInstance()->forgetInstance('config');
    }

    public function test_home_and_customer_pages_never_opt_in(): void
    {
        foreach (['/', '/user/dashboard', '/my-shop', '/@creator/events', '/api/v1/links'] as $path) {
            $this->assertNull(Standards::page($path), $path);
        }
        $home = file_get_contents(__DIR__.'/../../../resources/views/home.blade.php');
        $this->assertStringNotContainsString('MarketingPageStandards', $home);
        $this->assertStringNotContainsString('marketing-page-standards.css', $home);
    }

    public function test_every_registered_marketing_url_has_metadata_and_related_links(): void
    {
        foreach (Standards::pages() as $path => $page) {
            $this->assertNotSame('/', $path);
            $this->assertSame($path, Standards::page($path)['url']);
            $seo = Standards::seo(['title' => '', 'description' => ''], $page);
            $this->assertNotEmpty($seo['title'], $path);
            $this->assertNotEmpty($seo['description'], $path);
            $this->assertDoesNotMatchRegularExpression('/<[^>]+>/', $seo['description']);
            $this->assertLessThanOrEqual(170, mb_strlen($seo['description']));
            foreach (Standards::related($page) as $related => $label) {
                $this->assertNotSame($path, $related);
                $this->assertArrayHasKey($related, Standards::pages());
                $this->assertNotEmpty($label);
            }
        }
        $paths = array_column(MarketingSeo::sitemapPaths(), 'path');
        $this->assertContains('/android', $paths);
        $this->assertContains('/ai-dashboard', $paths);
    }

    public function test_explicit_metadata_wins_and_missing_description_uses_view_section(): void
    {
        $page = Standards::page('/android');
        $explicit = ['title' => 'Our custom Android title', 'description' => 'Our custom release description', 'share_image' => '/share.png'];
        $this->assertSame($explicit, Standards::seo($explicit, $page));
        $seo = Standards::seo(['title' => 'Android', 'description' => ''], $page, ['description' => ' Download <b>Android</b>   now. ']);
        $this->assertSame('Download Android now.', $seo['description']);
        $this->assertSame($explicit, Standards::seo($explicit, null));
    }

    public function test_pagination_and_private_search_indexing_are_deliberate(): void
    {
        $this->assertSame('https://sayzio.app/blogs?page=2', Standards::canonical('https://sayzio.app/blogs', ['page' => '2', 'utm_source' => 'newsletter']));
        $this->assertSame('https://sayzio.app/blogs', Standards::canonical('https://sayzio.app/blogs', ['page' => '-1']));
        $this->assertSame('noindex, follow', Standards::robots('/blogs', ['q' => 'help']));
        $this->assertSame('noindex, follow', Standards::robots('/privacy/request', []));
        $this->assertNull(Standards::robots('/features', []));
        $this->assertSame('noindex, follow', Standards::robots('/blogs', ['q' => ['invalid']]));
        $this->assertNotNull(Standards::page('/privacy/request/verify/test-token'));
        $this->assertArrayHasKey('android-download', MarketingSeo::codeDrivenDefaults());
        $this->assertNotNull(Standards::page('/blogs/category/guides'));
        $this->assertNotNull(Standards::page('/privacy/history'));
    }
}
