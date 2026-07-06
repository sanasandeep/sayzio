<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\Brand\AiBrandKitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Focused coverage for the real {@see AiBrandKitService::generate()} and its
 * apply paths (Task #2662).
 *
 * The contract under test:
 *   1. a successful generation persists a BrandKit and leaves the charge
 *      standing (no refund), tagging the chat call with the `brand_kit`
 *      feature so OpenAiService charges the right meter;
 *   2. an unparseable / invalid response refunds the EXACT credits charged
 *      against the `brand_kit` feature and persists nothing;
 *   3. applying a kit writes the palette/fonts/block-theme onto a biolink
 *      and the palette onto a QR code design;
 *   4. the per-plan `max_brand_kits` quantity cap gates creation (0 ⇒ the
 *      plan-less upgrade-prompt path).
 *
 * OpenAiService::chat() is a Mockery double (no network), AiUsageCharger is a
 * Mockery spy so the refund branch can be asserted precisely.
 */
class AiBrandKitTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{messages:array,opts:array}> */
    protected array $chatCalls = [];

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function plan(int $maxBrandKits = 5): Plan
    {
        return Plan::create([
            'name'          => 'Brand Plan',
            'slug'          => 'brand-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => [
                'max_links'      => 100,
                'max_biolinks'   => 100,
                'max_brand_kits' => $maxBrandKits,
            ],
        ]);
    }

    private function makeUser(Plan $plan): User
    {
        return User::factory()->create([
            'role' => 'user',
            'plan_id' => $plan->id,
        ])->fresh();
    }

    private function biolink(User $user): Link
    {
        return Link::create([
            'user_id'      => $user->id,
            'workspace_id' => app(WorkspaceContext::class)->resolve($user)?->id,
            'type'         => 'biolink',
            'alias'        => Str::random(8),
            'title'        => 'My page',
            'is_active'    => true,
        ]);
    }

    private function qrCode(User $user): QrCode
    {
        return QrCode::create([
            'user_id' => $user->id,
            'name'    => 'My QR',
            'type'    => 'url',
            'payload' => ['url' => 'https://example.test'],
            'design'  => ['fg_color' => '#000000', 'bg_color' => '#ffffff'],
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

    private function service(): AiBrandKitService
    {
        return app(AiBrandKitService::class);
    }

    private function validKitJson(): string
    {
        return json_encode([
            'name'    => 'Aurora Studio',
            'palette' => [
                'primary'   => '#3B5BDB',
                'secondary' => '#5C7CFA',
                'accent'    => '#F783AC',
                'neutrals'  => ['#F8F9FA', '#212529'],
            ],
            'fonts'    => ['heading' => 'Poppins', 'body' => 'Inter'],
            'voice'    => ['tone' => 'Warm and confident', 'descriptors' => ['friendly', 'premium']],
            'taglines' => ['Shine brighter', 'Your brand, elevated'],
            'bio'      => 'A modern studio helping creators look the part.',
            // 'minimal' is always a valid BiolinkBlock template key.
            'block_theme' => 'minimal',
        ]);
    }

    // ── 1. success persists a kit + charge stands ──────────────────────

    public function test_generate_persists_kit_and_keeps_charge(): void
    {
        $user = $this->makeUser($this->plan());
        $this->bindChat($this->validKitJson(), 9);
        $charger = $this->spyCharger();

        $result = $this->service()->generate($user, 'A modern studio for creators.');

        // The chat call carried the brand_kit feature (right meter).
        $this->assertCount(1, $this->chatCalls);
        $this->assertSame(AiBrandKitService::FEATURE, $this->chatCalls[0]['opts']['feature'] ?? null);

        $this->assertSame(9, $result['credits_spent']);

        $kit = $result['kit'];
        $this->assertInstanceOf(BrandKit::class, $kit);
        $this->assertTrue($kit->exists);
        $this->assertSame($user->id, $kit->user_id);
        $this->assertSame('#3b5bdb', $kit->config['palette']['primary']);
        $this->assertSame('Poppins', $kit->config['fonts']['heading']);
        $this->assertSame('minimal', $kit->config['block_theme']);
        $this->assertSame(1, BrandKit::where('user_id', $user->id)->count());

        // Success ⇒ no refund.
        $charger->shouldNotHaveReceived('refund');
    }

    // ── 2. unparseable response → exact refund, nothing persisted ──────

    public function test_generate_refunds_credits_when_response_unparseable(): void
    {
        $user = $this->makeUser($this->plan());
        $this->bindChat('this is definitely not json', 12);
        $charger = $this->spyCharger();

        try {
            $this->service()->generate($user, 'Make me a brand kit');
            $this->fail('Expected generate() to throw on unparseable output.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(0, BrandKit::where('user_id', $user->id)->count());
        $charger->shouldHaveReceived('refund')->once()->with(
            Mockery::on(fn ($u) => $u instanceof User && $u->id === $user->id),
            12,
            Mockery::on(fn ($o) => is_array($o) && ($o['feature'] ?? null) === AiBrandKitService::FEATURE),
        );
    }

    // ── 3. valid JSON but no usable palette → refund ───────────────────

    public function test_generate_refunds_when_palette_invalid(): void
    {
        $user = $this->makeUser($this->plan());
        // Valid JSON, but the primary color is not a hex ⇒ validation fails.
        $this->bindChat(json_encode([
            'name'    => 'Bad Kit',
            'palette' => ['primary' => 'not-a-color'],
            'fonts'   => ['heading' => 'Inter', 'body' => 'Inter'],
        ]), 4);
        $charger = $this->spyCharger();

        try {
            $this->service()->generate($user, 'Make me a brand kit');
            $this->fail('Expected generate() to throw when the palette is invalid.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(0, BrandKit::where('user_id', $user->id)->count());
        $charger->shouldHaveReceived('refund')->once()->with(
            Mockery::on(fn ($u) => $u instanceof User && $u->id === $user->id),
            4,
            Mockery::on(fn ($o) => is_array($o) && ($o['feature'] ?? null) === AiBrandKitService::FEATURE),
        );
    }

    // ── 4. apply to a biolink writes palette/fonts/theme ───────────────

    public function test_apply_to_biolink_writes_palette_fonts_and_theme(): void
    {
        $user = $this->makeUser($this->plan());
        $link = $this->biolink($user);
        $this->bindChat($this->validKitJson(), 3);
        $this->spyCharger();

        $kit = $this->service()->generate($user, 'A modern studio.')['kit'];
        $this->service()->applyToBiolink($kit, $link->fresh());

        $bio = $link->fresh()->settings['biolink'] ?? [];
        $this->assertSame('Inter', $bio['font_family'] ?? null);     // body font
        $this->assertSame('#3b5bdb', $bio['button_color'] ?? null);  // primary
        $this->assertSame('#212529', $bio['font_color'] ?? null);    // dark neutral
        $this->assertIsArray($bio['block_theme'] ?? null);
        $this->assertSame('minimal', $bio['block_theme']['_template'] ?? null);
        $this->assertTrue($bio['block_theme']['apply_to_all'] ?? false);
        $this->assertSame('Poppins', $bio['block_theme']['font_family'] ?? null); // heading
    }

    // ── 5. apply to a QR code writes the palette into the design ───────

    public function test_apply_to_qr_writes_palette_into_design(): void
    {
        $user = $this->makeUser($this->plan());
        $qr   = $this->qrCode($user);
        $this->bindChat($this->validKitJson(), 3);
        $this->spyCharger();

        $kit = $this->service()->generate($user, 'A modern studio.')['kit'];
        $this->service()->applyToQr($kit, $qr->fresh());

        $design = $qr->fresh()->design ?? [];
        $this->assertSame('#212529', $design['fg_color'] ?? null);          // dark neutral
        $this->assertSame('#f8f9fa', $design['bg_color'] ?? null);          // light neutral
        $this->assertSame('#3b5bdb', $design['corner_square_color'] ?? null); // primary
    }

    // ── 6. plan cap gates creation (0 ⇒ upgrade prompt path) ──────────

    public function test_quantity_cap_blocks_creation_when_zero(): void
    {
        $user = $this->makeUser($this->plan(0));

        $this->assertSame(0, AiPlanAccess::quantityCap($user, 'brand_kits'));
        $this->assertFalse(AiPlanAccess::underQuantityCap($user, 'brand_kits', 0));
    }

    public function test_quantity_cap_allows_until_limit(): void
    {
        $user = $this->makeUser($this->plan(2));

        $this->assertTrue(AiPlanAccess::underQuantityCap($user, 'brand_kits', 0));
        $this->assertTrue(AiPlanAccess::underQuantityCap($user, 'brand_kits', 1));
        $this->assertFalse(AiPlanAccess::underQuantityCap($user, 'brand_kits', 2));
    }
}
