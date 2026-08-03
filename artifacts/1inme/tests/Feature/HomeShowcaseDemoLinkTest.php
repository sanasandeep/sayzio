<?php

namespace Tests\Feature;

use App\Modules\Common\Controllers\SitePageController;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Database\Seeders\LinkTypeExplainerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Guards the home "What you can create" showcase → /demos explainer mapping.
 *
 * Each home card maps BY NAME to a seeded `demo-type-{slug(name)}` biolink
 * (LinkTypeExplainerSeeder). If a seeder rename or an admin rename of the
 * `home` SitePage `extra.link_types` breaks that mapping, the card silently
 * falls back to the generic Features anchor and nobody notices the demo went
 * dark. This suite catches the drift in the normal test run:
 *
 *  1. every default homeLinkTypesDefault() name must resolve to a page the
 *     seeder actually seeds (or be explicitly allow-listed as demo-less);
 *  2. when the demo link exists and is active, the card renders the demo
 *     href ("See demo");
 *  3. when the demo is absent, the card renders the Features-anchor
 *     fallback ("Learn more") instead of a dead link;
 *  4. a cache failure while resolving the demo alias set degrades to the
 *     Features fallback for every card — it must never 500 the home page.
 */
class HomeShowcaseDemoLinkTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Default showcase names that intentionally have NO seeded
     * `demo-type-*` explainer page. Add a name here ONLY when the card is
     * deliberately demo-less; anything else failing the drift test below
     * means the seeder and the showcase copy have diverged.
     *
     * @var list<string>
     */
    private const DEMOLESS_SHOWCASE_NAMES = [];

    /** Aliases the seeder actually seeds, read from its private pages() catalog. */
    private function seededAliases(): array
    {
        $method = new \ReflectionMethod(LinkTypeExplainerSeeder::class, 'pages');
        $pages = $method->invoke(new LinkTypeExplainerSeeder());

        return array_values(array_filter(array_map(
            fn (array $p) => (string) ($p['alias'] ?? ''),
            $pages
        )));
    }

    public function test_every_default_showcase_name_resolves_to_a_seeded_demo_page(): void
    {
        $seeded = array_fill_keys($this->seededAliases(), true);

        $missing = [];
        foreach (SitePagesContent::homeLinkTypesDefault() as $lt) {
            $name = trim((string) ($lt['name'] ?? ''));
            if ($name === '' || in_array($name, self::DEMOLESS_SHOWCASE_NAMES, true)) {
                continue;
            }
            $alias = 'demo-type-' . Str::slug($name);
            if (! isset($seeded[$alias])) {
                $missing[] = "{$name} → {$alias}";
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Home showcase card(s) no longer map to a seeded demo explainer page.\n"
                . "Either fix the name/alias in LinkTypeExplainerSeeder::pages() / "
                . "SitePagesContent::homeLinkTypesDefault(), or add the name to "
                . "DEMOLESS_SHOWCASE_NAMES if the card is intentionally demo-less:\n"
                . implode("\n", $missing)
        );
    }

    private function makeDemoLink(string $alias, string $title): Link
    {
        $user = User::factory()->create();

        return Link::create([
            'user_id'   => $user->id,
            'type'      => 'biolink',
            'alias'     => $alias,
            'title'     => $title,
            'is_active' => true,
        ]);
    }

    public function test_home_card_links_to_demo_page_when_demo_link_exists(): void
    {
        Cache::flush();
        $this->makeDemoLink('demo-type-short-link', 'Short Link, explained');

        $resp = $this->get(route('home.sections'));
        $resp->assertOk();
        // The card deep-links to the live explainer with the demo label…
        $resp->assertSee('See the live Short Link demo');
        $resp->assertSee(url('/demo-type-short-link'), false);
        // …and does NOT render the card's Features-anchor fallback aria.
        $resp->assertDontSee('Short Link — learn more on the Features page');
    }

    public function test_home_card_falls_back_to_features_anchor_when_demo_absent(): void
    {
        Cache::flush();
        // No demo-type-* links exist at all.

        $resp = $this->get(route('home.sections'));
        $resp->assertOk();
        // No card claims a live demo…
        $resp->assertDontSee('See the live Short Link demo');
        // …the Short Link card itself renders its card-specific fallback
        // aria (the generic Features anchor also exists in a page-wide CTA,
        // so asserting only the href would false-pass).
        $resp->assertSee('Short Link — learn more on the Features page');
        // …and never links a dead demo URL.
        $resp->assertDontSee(url('/demo-type-short-link'), false);
    }

    public function test_home_render_survives_demo_cache_failure(): void
    {
        Cache::flush();
        // Even with a seeded demo, a cache-layer failure while resolving
        // the alias set must degrade to the Features fallback — not 500.
        $this->makeDemoLink('demo-type-short-link', 'Short Link, explained');

        // Proxy-partial around the REAL cache manager (Cache::partialMock()
        // would build a fresh CacheManager with a null $app and crash), so
        // every un-mocked call keeps working against the real stores.
        $mock = \Mockery::mock(Cache::getFacadeRoot())->makePartial();
        $mock->shouldReceive('remember')
            ->withArgs(fn ($key) => $key === SitePageController::DEMOS_CACHE_KEY)
            ->andThrow(new \RuntimeException('cache backend down'));
        // Every other remember() call behaves normally (executes its closure).
        $mock->shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());
        Cache::swap($mock);

        $resp = $this->get(route('home.sections'));
        $resp->assertOk();
        $resp->assertDontSee('See the live Short Link demo');
        $resp->assertSee(route('site.features') . '#cat-link-types', false);
    }
}
