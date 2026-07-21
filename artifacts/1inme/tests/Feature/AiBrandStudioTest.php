<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\User;
use App\Services\AI\AiUsageCharger;
use App\Services\AI\OpenAiService;
use App\Services\Brand\AiBrandStudioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Focused coverage for the real {@see AiBrandStudioService} (Task #5551).
 *
 * The contract under test:
 *   1. a successful plan() persists a BrandStudioKit proposal, tags the chat
 *      call with the `brand_studio` feature, and leaves the charge standing;
 *   2. an unparseable response refunds the EXACT credits charged against
 *      `brand_studio` and persists nothing;
 *   3. materialize() creates the kept assets through the real creation paths
 *      and flips the kit to `created`, honoring the `keep` index list;
 *   4. per-type plan caps skip (not fail) capped assets and report them;
 *   5. bulkCap() derives from `max_brand_studio_bulk` and clamps to the hard
 *      per-run ceiling.
 *
 * OpenAiService::chat() is a Mockery double (no network), AiUsageCharger is a
 * Mockery spy so the refund branch can be asserted precisely.
 */
class AiBrandStudioTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{messages:array,opts:array}> */
    protected array $chatCalls = [];

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function plan(array $features = []): Plan
    {
        return Plan::create([
            'name'          => 'Studio Plan',
            'slug'          => 'studio-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => array_merge([
                'max_links'             => 100,
                'max_biolinks'          => 100,
                'max_qr_codes'          => 100,
                'max_forms'             => 100,
                'brand_studio'          => true,
                'max_brand_studio_bulk' => 20,
            ], $features),
        ]);
    }

    private function makeUser(Plan $plan): User
    {
        return User::factory()->create([
            'role'    => 'user',
            'plan_id' => $plan->id,
        ])->fresh();
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

    private function service(): AiBrandStudioService
    {
        return app(AiBrandStudioService::class);
    }

    private function validPlanJson(): string
    {
        return json_encode([
            'name'   => 'Summer Launch Kit',
            'assets' => [
                [
                    'kind'   => 'biolink',
                    'title'  => 'Summer Launch',
                    'theme_color' => '#2fb4ff',
                    'blocks' => [
                        ['type' => 'heading', 'settings' => ['text' => 'Summer sale is live', 'size' => 'h2']],
                        ['type' => 'cta_button', 'settings' => ['text' => 'Shop now', 'url' => 'https://example.test/shop']],
                    ],
                ],
                ['kind' => 'short_link', 'title' => 'Shop link', 'url' => 'https://example.test/shop'],
                ['kind' => 'qr_code', 'name' => 'Poster QR', 'url' => 'https://example.test/shop'],
                ['kind' => 'form', 'title' => 'Leads', 'template' => 'contact', 'description' => 'Get in touch'],
                ['kind' => 'vcard', 'first_name' => 'Ava', 'last_name' => 'Nguyen', 'organization' => 'Nova Coffee'],
            ],
        ]);
    }

    private function planKit(User $user, int $credits = 8): BrandStudioKit
    {
        $this->bindChat($this->validPlanJson(), $credits);
        $result = $this->service()->plan($user, 'Launch our summer sale.', '', [], 'kit', null, 1);
        return $result['kit'];
    }

    // ── 1. successful plan persists a proposal + charge stands ─────────

    public function test_plan_persists_proposal_and_keeps_charge(): void
    {
        $user = $this->makeUser($this->plan());
        $this->bindChat($this->validPlanJson(), 8);
        $charger = $this->spyCharger();

        $result = $this->service()->plan($user, 'Launch our summer sale.', '', [], 'kit', null, 1);

        $this->assertCount(1, $this->chatCalls);
        $this->assertSame(AiBrandStudioService::FEATURE, $this->chatCalls[0]['opts']['feature'] ?? null);
        $this->assertSame(8, $result['credits_spent']);

        $kit = $result['kit'];
        $this->assertInstanceOf(BrandStudioKit::class, $kit);
        $this->assertTrue($kit->exists);
        $this->assertSame($user->id, $kit->user_id);
        $this->assertSame(BrandStudioKit::STATUS_PROPOSAL, $kit->status);
        $this->assertSame('Summer Launch Kit', $kit->name);
        $this->assertCount(5, $kit->proposedAssets());

        $charger->shouldNotHaveReceived('refund');
    }

    // ── 2. unparseable response → exact refund, nothing persisted ──────

    public function test_plan_refunds_credits_when_response_unparseable(): void
    {
        $user = $this->makeUser($this->plan());
        $this->bindChat('definitely not json', 11);
        $charger = $this->spyCharger();

        try {
            $this->service()->plan($user, 'Launch our summer sale.', '', [], 'kit', null, 1);
            $this->fail('Expected plan() to throw on unparseable output.');
        } catch (\RuntimeException $e) {
            // expected
        }

        $this->assertSame(0, BrandStudioKit::where('user_id', $user->id)->count());
        $charger->shouldHaveReceived('refund')->once()->with(
            Mockery::on(fn ($u) => $u instanceof User && $u->id === $user->id),
            11,
            Mockery::on(fn ($meta) => ($meta['feature'] ?? null) === AiBrandStudioService::FEATURE),
        );
    }

    // ── 3. materialize creates kept assets and flips status ────────────

    public function test_materialize_creates_kept_assets(): void
    {
        $user = $this->makeUser($this->plan());
        $kit  = $this->planKit($user);

        // Keep everything except the vCard (index 4).
        $result = $this->service()->materialize($user, $kit, [0, 1, 2, 3]);

        $this->assertSame(4, $result['created']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame(BrandStudioKit::STATUS_CREATED, $result['kit']->status);

        $links = Link::withoutGlobalScope('workspace')->where('user_id', $user->id)->get();
        $this->assertSame(1, $links->where('type', 'biolink')->count());
        $this->assertSame(1, $links->where('type', 'url')->count());
        $this->assertSame(0, $links->where('type', 'vcf')->count());
        $this->assertSame(1, QrCode::withoutGlobalScope('workspace')->where('user_id', $user->id)->count());
        $this->assertSame(1, Form::withoutGlobalScope('workspace')->where('user_id', $user->id)->count());
    }

    // ── 4. plan caps skip capped assets, create the rest ───────────────

    public function test_materialize_skips_assets_over_plan_caps(): void
    {
        $user = $this->makeUser($this->plan(['max_qr_codes' => 0, 'max_forms' => 0]));
        $kit  = $this->planKit($user);

        $result = $this->service()->materialize($user, $kit);

        // biolink + short link + vcard created; QR + form skipped.
        $this->assertSame(3, $result['created']);
        $this->assertNotEmpty($result['skipped']);
        $this->assertSame(0, QrCode::withoutGlobalScope('workspace')->where('user_id', $user->id)->count());
        $this->assertSame(0, Form::withoutGlobalScope('workspace')->where('user_id', $user->id)->count());
        $this->assertSame(BrandStudioKit::STATUS_CREATED, $result['kit']->status);
    }

    // ── 5. bulk cap derives from the plan and clamps to the ceiling ────

    public function test_bulk_cap_from_plan_and_hard_ceiling(): void
    {
        $capped = $this->makeUser($this->plan(['max_brand_studio_bulk' => 5]));
        $this->assertSame(5, AiBrandStudioService::bulkCap($capped));

        $unlimited = $this->makeUser($this->plan(['max_brand_studio_bulk' => -1]));
        $this->assertSame(AiBrandStudioService::HARD_BULK_CAP, AiBrandStudioService::bulkCap($unlimited));

        $none = $this->makeUser($this->plan(['max_brand_studio_bulk' => 0]));
        $this->assertSame(0, AiBrandStudioService::bulkCap($none));
    }
}
