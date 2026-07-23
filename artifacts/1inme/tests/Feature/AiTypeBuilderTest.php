<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkSlide;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\ServiceBookingService;
use App\Modules\User\Models\StoreProduct;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\Builder\AiResumeBuilderService;
use App\Services\AI\Builder\AiRestaurantMenuBuilderService;
use App\Services\AI\Builder\AiServiceBookingBuilderService;
use App\Services\AI\Builder\AiSlidesBuilderService;
use App\Services\AI\Builder\AiStoreMenuBuilderService;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Coverage for the per-type AI builders (Task #5727): Slides, Restaurant
 * Menu, Store, Service Booking, Resume.
 *
 * OpenAiService::chat() is a Mockery double (no network, we control the
 * "model" output + reported credits) and AiUsageCharger is a spy so the
 * refund contract can be asserted exactly:
 *   - success   → charge stands, rows materialized;
 *   - bad JSON  → exact refund, nothing persisted;
 *   - no items  → exact refund, nothing persisted.
 */
class AiTypeBuilderTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{messages:array,opts:array}> */
    protected array $chatCalls = [];

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeUser(): User
    {
        $plan = Plan::create([
            'name'          => 'Type Builder Plan',
            'slug'          => 'type-builder-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => ['max_links' => 100, 'max_biolinks' => 100],
        ]);

        return User::factory()->create(['role' => 'user', 'plan_id' => $plan->id])->fresh();
    }

    private function link(User $user, string $type): Link
    {
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => app(WorkspaceContext::class)->resolve($user)?->id,
            'type'         => $type,
            'alias'        => Str::random(8),
            'title'        => 'My ' . $type,
            'is_active'    => true,
        ]);
    }

    private function bindChat(string $content, int $creditsSpent): void
    {
        $calls =& $this->chatCalls;
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')
            ->andReturnUsing(function ($user, $model, $messages, $opts = []) use (&$calls, $content, $creditsSpent) {
                $calls[] = ['messages' => $messages, 'opts' => $opts];
                return [
                    'content'       => $content,
                    'tool_calls'    => [],
                    'finish_reason' => 'stop',
                    'tokens_in'     => 0,
                    'tokens_out'    => 0,
                    'credits_spent' => $creditsSpent,
                    'model'         => $model,
                    'raw'           => [],
                ];
            });
        $this->app->instance(OpenAiService::class, $mock);
    }

    private function spyCharger(): \Mockery\MockInterface
    {
        $charger = Mockery::spy(AiUsageCharger::class);
        $this->app->instance(AiUsageCharger::class, $charger);
        return $charger;
    }

    // ── Slides ──────────────────────────────────────────────────────────

    public function test_slides_builder_creates_deck_slides_and_blocks(): void
    {
        $user = $this->makeUser();
        $link = $this->link($user, Link::TYPE_SLIDES);

        $image = '/f/1/pic.png';
        $this->bindChat(json_encode([
            'slides' => [
                [
                    'title'      => 'Intro',
                    'background' => ['type' => 'color', 'color' => '#112233'],
                    'blocks'     => [
                        ['type' => 'heading',   'settings' => ['text' => 'Hello', 'size' => 'h1']],
                        ['type' => 'list',      'settings' => ['items' => ['One', 'Two']]],
                        ['type' => 'image',     'settings' => ['url' => $image, 'alt' => 'ok']],
                        ['type' => 'image',     'settings' => ['url' => 'https://evil.test/x.png']],
                        ['type' => 'evil_type', 'settings' => ['text' => 'nope']],
                    ],
                ],
                ['title' => 'Empty', 'blocks' => []],
                ['title' => 'Two', 'blocks' => [['type' => 'paragraph', 'settings' => ['text' => 'Body copy']]]],
            ],
        ]), 5);
        $charger = $this->spyCharger();

        $result = app(AiSlidesBuilderService::class)->generate($user, $link, 'A deck about my studio.', [], [$image]);

        $this->assertSame(5, $result['credits_spent']);
        $this->assertSame(AiSlidesBuilderService::FEATURE, $this->chatCalls[0]['opts']['feature'] ?? null);
        $this->assertSame($link->id, $this->chatCalls[0]['opts']['related_id'] ?? null);

        // Empty slide dropped; invented block type + non-supplied image dropped.
        $this->assertSame(2, $result['summary']['slides']);
        $slides = LinkSlide::whereHas('deck', fn ($q) => $q->where('link_id', $link->id))->orderBy('sort_order')->get();
        $this->assertCount(2, $slides);
        $this->assertSame('Intro', $slides[0]->title);
        $this->assertSame(['type' => 'color', 'color' => '#112233'], $slides[0]->background);

        $blocks = BiolinkBlock::where('link_id', $link->id)->get();
        $this->assertCount(4, $blocks); // heading, list, supplied image, paragraph
        $this->assertEqualsCanonicalizing(['heading', 'list', 'image', 'paragraph'], $blocks->pluck('type')->all());
        $imageBlock = $blocks->firstWhere('type', 'image');
        $this->assertSame($image, $imageBlock->settings['url']); // kept relative
        // List items persist as flat strings (public renderer contract).
        $this->assertSame(['One', 'Two'], $blocks->firstWhere('type', 'list')->settings['items']);

        $charger->shouldNotHaveReceived('refund');
    }

    // ── Restaurant Menu ─────────────────────────────────────────────────

    public function test_restaurant_builder_materializes_categories_and_items(): void
    {
        $user = $this->makeUser();
        $link = $this->link($user, Link::TYPE_RESTAURANT_MENU);

        $this->bindChat(json_encode([
            'currency'   => 'eur',
            'categories' => [
                ['name' => 'Antipasti', 'items' => [
                    ['name' => 'Bruschetta', 'description' => 'Grilled bread', 'price' => 6.5],
                    ['name' => 'Olives', 'price' => -3], // clamped to 0
                ]],
                ['items' => [['name' => 'Orphan', 'price' => 1]]], // no name → dropped
            ],
        ]), 4);
        $charger = $this->spyCharger();

        $result = app(AiRestaurantMenuBuilderService::class)->generate($user, $link, 'Italian trattoria menu.', [], []);

        $this->assertSame(['categories' => 1, 'items' => 2], $result['summary']);
        $menu = RestaurantMenu::where('link_id', $link->id)->first();
        $this->assertNotNull($menu);
        $this->assertSame('EUR', $menu->currency);
        $items = RestaurantMenuItem::where('menu_id', $menu->id)->orderBy('sort_order')->get();
        $this->assertSame('Bruschetta', $items[0]->name);
        $this->assertEquals(0, (float) $items[1]->price);
        $charger->shouldNotHaveReceived('refund');
    }

    public function test_restaurant_builder_refunds_on_invalid_json(): void
    {
        $user = $this->makeUser();
        $link = $this->link($user, Link::TYPE_RESTAURANT_MENU);

        $this->bindChat('Sorry, here is a menu in prose form…', 9);
        $charger = $this->spyCharger();

        try {
            app(AiRestaurantMenuBuilderService::class)->generate($user, $link, 'Italian trattoria menu.', [], []);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            // expected
        }

        $charger->shouldHaveReceived('refund')->once()->with(
            Mockery::on(fn ($u) => $u->id === $user->id),
            9,
            Mockery::on(fn ($opts) => ($opts['feature'] ?? null) === AiRestaurantMenuBuilderService::FEATURE
                && ($opts['related_id'] ?? null) === $link->id),
        );
        $this->assertSame(0, RestaurantMenuItem::count());
    }

    public function test_restaurant_builder_refunds_when_no_usable_items(): void
    {
        $user = $this->makeUser();
        $link = $this->link($user, Link::TYPE_RESTAURANT_MENU);

        $this->bindChat(json_encode(['categories' => [['name' => 'Empty', 'items' => []]]]), 3);
        $charger = $this->spyCharger();

        try {
            app(AiRestaurantMenuBuilderService::class)->generate($user, $link, 'Menu please.', [], []);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            // expected
        }

        $charger->shouldHaveReceived('refund')->once();
        // The transaction rolled back — not even the empty category persists.
        $this->assertSame(0, \App\Modules\User\Models\RestaurantMenuCategory::count());
    }

    // ── Store ───────────────────────────────────────────────────────────

    public function test_store_builder_materializes_products(): void
    {
        $user = $this->makeUser();
        $link = $this->link($user, Link::TYPE_STORE_MENU);

        $this->bindChat(json_encode([
            'currency'   => 'USD',
            'categories' => [
                ['name' => 'Candles', 'products' => [
                    ['name' => 'Vanilla Candle', 'price' => 18],
                    ['name' => 'Lavender Candle', 'price' => 22, 'photo_url' => 'https://not-supplied.test/x.jpg'],
                ]],
            ],
        ]), 6);
        $charger = $this->spyCharger();

        $result = app(AiStoreMenuBuilderService::class)->generate($user, $link, 'Handmade candle store.', [], []);

        $this->assertSame(['categories' => 1, 'products' => 2], $result['summary']);
        $products = StoreProduct::orderBy('sort_order')->get();
        $this->assertCount(2, $products);
        $this->assertNull($products[1]->photo_url); // non-supplied image stripped
        $charger->shouldNotHaveReceived('refund');
    }

    // ── Service Booking ─────────────────────────────────────────────────

    public function test_service_booking_builder_clamps_durations(): void
    {
        $user = $this->makeUser();
        $link = $this->link($user, Link::TYPE_SERVICE_BOOKING);

        $this->bindChat(json_encode([
            'currency'   => 'GBP',
            'categories' => [
                ['name' => 'Hair', 'services' => [
                    ['name' => 'Cut', 'price' => 30, 'duration_minutes' => 45],
                    ['name' => 'Marathon', 'price' => 500, 'duration_minutes' => 99999],
                    ['name' => 'Blink', 'price' => 1, 'duration_minutes' => 1],
                ]],
            ],
        ]), 2);
        $charger = $this->spyCharger();

        $result = app(AiServiceBookingBuilderService::class)->generate($user, $link, 'Barbershop services.', [], []);

        $this->assertSame(['categories' => 1, 'services' => 3], $result['summary']);
        $services = ServiceBookingService::orderBy('sort_order')->get();
        $this->assertSame([45, 1440, 5], $services->pluck('duration_minutes')->all());
        $charger->shouldNotHaveReceived('refund');
    }

    // ── Resume ──────────────────────────────────────────────────────────

    public function test_resume_builder_fills_header_and_sections(): void
    {
        $user = $this->makeUser();
        $link = $this->link($user, Link::TYPE_RESUME);

        $suppliedUrl = 'https://github.com/example';
        $this->bindChat(json_encode([
            'header'  => ['name' => 'Ada Example', 'headline' => 'Senior Engineer'],
            'summary' => 'Engineer with 8 years of experience.',
            'sections' => [
                'experience' => [
                    ['company' => 'Acme', 'role' => 'Engineer', 'start_date' => '2020-01', 'end_date' => 'not-a-date', 'description' => 'Built things.'],
                    ['role' => 'Ghost'], // no company → dropped
                ],
                'skills' => [
                    ['name' => 'React', 'level' => 5],
                    ['name' => 'Zen', 'level' => 99], // invalid level dropped, name kept
                ],
                'links' => [
                    ['label' => 'GitHub', 'url' => $suppliedUrl],
                    ['label' => 'Invented', 'url' => 'https://invented.test'], // not supplied → dropped
                ],
            ],
        ]), 8);
        $charger = $this->spyCharger();

        $result = app(AiResumeBuilderService::class)->generate($user, $link, 'Senior engineer resume.', [$suppliedUrl], []);

        $this->assertSame(8, $result['credits_spent']);
        $resume = $user->fresh()->ensureResume();
        $sections = $resume->getMergedSections();
        $this->assertSame('Ada Example', $sections['header']['name']);
        $this->assertSame('Engineer with 8 years of experience.', $sections['summary']);

        $items = ResumeSectionItem::where('resume_id', $resume->id)->get();
        $this->assertSame(1, $items->where('section_type', 'experience')->count());
        $exp = $items->firstWhere('section_type', 'experience')->data;
        $this->assertSame('2020-01', $exp['start_date']);
        $this->assertArrayNotHasKey('end_date', $exp); // invalid date dropped

        $skills = $items->where('section_type', 'skills')->values();
        $this->assertCount(2, $skills);
        $this->assertArrayNotHasKey('level', $skills[1]->data);

        $links = $items->where('section_type', 'links')->values();
        $this->assertCount(1, $links);
        $this->assertSame($suppliedUrl, $links[0]->data['url']);

        $charger->shouldNotHaveReceived('refund');
    }

    // ── HTTP endpoints ──────────────────────────────────────────────────

    public function test_generate_endpoint_materializes_and_redirects_to_editor(): void
    {
        \App\Services\AI\AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();
        $link = $this->link($user, Link::TYPE_RESTAURANT_MENU);

        $this->bindChat(json_encode([
            'categories' => [['name' => 'Mains', 'items' => [['name' => 'Pasta', 'price' => 12]]]],
        ]), 3);
        $this->spyCharger();

        $res = $this->actingAs($user)->postJson(route('user.links.ai-type-builder.generate', $link), [
            'description' => 'A small pasta place menu.',
        ]);

        $res->assertOk()
            ->assertJsonPath('credits_spent', 3)
            ->assertJsonPath('summary.items', 1)
            ->assertJsonPath('redirect', route('user.links.restaurant.editor', $link));
    }

    public function test_generate_endpoint_rejects_wrong_type_and_foreign_link(): void
    {
        \App\Services\AI\AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();

        // A plain short link has no AI type builder.
        $shortLink = Link::create([
            'user_id'      => $user->id,
            'workspace_id' => app(WorkspaceContext::class)->resolve($user)?->id,
            'type'         => 'short',
            'alias'        => Str::random(8),
            'url'          => 'https://example.com',
            'is_active'    => true,
        ]);
        $this->actingAs($user)
            ->postJson(route('user.links.ai-type-builder.generate', $shortLink), ['description' => 'Ten characters plus.'])
            ->assertNotFound();

        // Someone else's link 404s before any AI work happens.
        $other     = $this->makeUser();
        $otherLink = $this->link($other, Link::TYPE_SLIDES);
        $this->actingAs($user)
            ->postJson(route('user.links.ai-type-builder.generate', $otherLink), ['description' => 'Ten characters plus.'])
            ->assertNotFound();
    }

    public function test_intake_screen_renders_for_each_type(): void
    {
        \App\Services\AI\AiEngineSettings::setEnabled(true);
        $user = $this->makeUser();

        foreach ([Link::TYPE_SLIDES, Link::TYPE_RESTAURANT_MENU, Link::TYPE_STORE_MENU, Link::TYPE_SERVICE_BOOKING, Link::TYPE_RESUME] as $type) {
            $link = $this->link($user, $type);
            $this->actingAs($user)
                ->get(route('user.links.ai-type-builder', $link))
                ->assertOk()
                ->assertSee('Build your', false);
        }
    }
}
