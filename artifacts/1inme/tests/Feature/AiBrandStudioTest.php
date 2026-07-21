<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BrandStudioKit;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
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
            'role'         => 'user',
            'plan_id'      => $plan->id,
            'onboarded_at' => now(),
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

    // ── 6. composition: prompt injection is a hard requirement ─────────

    public function test_plan_prompt_contains_exact_composition_lines(): void
    {
        $user = $this->makeUser($this->plan());
        $this->bindChat($this->validPlanJson(), 8);
        $this->spyCharger();

        $composition = [
            ['kind' => 'biolink', 'count' => 2, 'purpose' => 'Product page'],
            ['kind' => 'vcard', 'count' => 1, 'purpose' => ''],
        ];
        $this->service()->plan($user, '', '', [], 'kit', null, 1, $composition);

        $this->assertCount(1, $this->chatCalls);
        $system = $this->chatCalls[0]['messages'][0]['content'];
        $this->assertStringContainsString('EXACT REQUESTED COMPOSITION', $system);
        $this->assertStringContainsString('Produce EXACTLY these 3 assets', $system);
        $this->assertStringContainsString("1. biolink — purpose: Product page", $system);
        $this->assertStringContainsString("2. biolink — purpose: Product page", $system);
        $this->assertStringContainsString("3. vcard", $system);
    }

    // ── 7. composition: post-validation repairs the returned plan ──────

    public function test_composition_repair_drops_unrequested_kinds_clamps_counts_and_attaches_purposes(): void
    {
        $user = $this->makeUser($this->plan());
        // AI ignores the composition: returns 2 biolinks, a QR (unrequested)
        // and a form (unrequested), while we asked for 1 biolink + 1 vcard.
        $this->bindChat(json_encode([
            'name'   => 'Stubborn Kit',
            'assets' => [
                ['kind' => 'biolink', 'title' => 'Page A', 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'A']]]],
                ['kind' => 'biolink', 'title' => 'Page B', 'blocks' => [['type' => 'heading', 'settings' => ['text' => 'B']]]],
                ['kind' => 'qr_code', 'name' => 'Rogue QR', 'url' => 'https://example.test'],
                ['kind' => 'form', 'title' => 'Rogue Form', 'template' => 'contact'],
                ['kind' => 'vcard', 'first_name' => 'Ava', 'last_name' => 'Nguyen'],
            ],
        ]), 8);
        $this->spyCharger();

        $composition = [
            ['kind' => 'biolink', 'count' => 1, 'purpose' => 'Main page'],
            ['kind' => 'vcard', 'count' => 1, 'purpose' => 'Business card'],
        ];
        $result = $this->service()->plan($user, '', '', [], 'kit', null, 1, $composition);

        $assets = $result['kit']->proposedAssets();
        $kinds  = array_column($assets, 'kind');
        sort($kinds);
        $this->assertSame(['biolink', 'vcard'], $kinds);

        foreach ($assets as $a) {
            if ($a['kind'] === 'biolink') {
                $this->assertSame('Main page', $a['purpose'] ?? null);
            }
            if ($a['kind'] === 'vcard') {
                $this->assertSame('Business card', $a['purpose'] ?? null);
            }
        }

        $this->assertSame($composition[0] + [], $result['kit']->proposal['composition'][0] ?? null);
    }

    // ── 8. composition validation enforces per-kind kit caps ───────────

    public function test_sanitize_composition_rejects_over_cap_and_unknown_kinds(): void
    {
        $rows = AiBrandStudioService::sanitizeComposition([
            ['kind' => 'biolink', 'count' => 2, 'purpose' => str_repeat('x', 500)],
        ]);
        $this->assertSame('biolink', $rows[0]['kind']);
        $this->assertSame(2, $rows[0]['count']);
        $this->assertSame(AiBrandStudioService::MAX_PURPOSE_LEN, mb_strlen($rows[0]['purpose']));

        try {
            AiBrandStudioService::sanitizeComposition([
                ['kind' => 'biolink', 'count' => 2],
                ['kind' => 'biolink', 'count' => 2],
            ]);
            $this->fail('Expected over-cap composition to throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('maximum per kit', $e->getMessage());
        }

        $this->expectException(\RuntimeException::class);
        AiBrandStudioService::sanitizeComposition([['kind' => 'unicorn', 'count' => 1]]);
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

    // ── 6. estimate endpoints mirror the real charge (Task #5569) ──────
    //
    // The web + API estimate endpoints must return exactly what
    // AiBrandStudioService::estimateCredits() computes for the same
    // payload — that method routes through the same estimateChatCoins /
    // per-plan multiplier path the real charge uses, so asserting
    // endpoint === service pins the "displayed price never drifts from
    // the charged price" contract.

    private const ESTIMATE_INLINE = ['name' => '', 'colors' => '', 'voice' => '', 'description' => ''];

    /** Service-computed estimate for the same args the controllers pass. */
    private function expectedEstimate(User $user, string $request, string $mode = 'kit', ?string $bulkKind = null, int $bulkCount = 5): int
    {
        $svc   = $this->service();
        $brand = $svc->resolveBrand($user, null, self::ESTIMATE_INLINE);
        return $svc->estimateCredits($user, $request, $brand['directives'], $mode, $bulkKind, $bulkCount);
    }

    public function test_web_estimate_matches_service_and_returns_balance(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser($this->plan());

        $resp = $this->actingAs($user)->postJson('/user/brand-studio/estimate', [
            'request' => 'Launch our summer sale.',
        ]);

        $resp->assertOk();
        $expected = $this->expectedEstimate($user, 'Launch our summer sale.');
        $this->assertGreaterThan(0, $expected, 'estimate should be non-zero with the engine on');
        $this->assertSame($expected, $resp->json('estimated_credits'));
        $this->assertIsInt($resp->json('balance'));
    }

    public function test_web_estimate_bulk_mode_reflects_bulk_prompt(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser($this->plan());

        // Sanity: the bulk prompt really is different from the kit prompt
        // for the same brief, so a bulk estimate reflects bulk pricing.
        $svc = $this->service();
        $bulkMessages = $svc->buildMessages($user, 'Promo links for our summer sale.', '', 'bulk', 'short_link', 10);
        $this->assertStringContainsString(
            'exactly 10 DISTINCT',
            implode("\n", array_column($bulkMessages, 'content')),
        );

        $resp = $this->actingAs($user)->postJson('/user/brand-studio/estimate', [
            'request'    => 'Promo links for our summer sale.',
            'mode'       => 'bulk',
            'bulk_kind'  => 'short_link',
            'bulk_count' => 10,
        ]);

        $resp->assertOk();
        $expected = $this->expectedEstimate($user, 'Promo links for our summer sale.', 'bulk', 'short_link', 10);
        $this->assertGreaterThan(0, $expected);
        $this->assertSame($expected, $resp->json('estimated_credits'));
        $this->assertIsInt($resp->json('balance'));
    }

    public function test_web_estimate_gated_by_engine_and_plan_flags(): void
    {
        // Engine off → 404 even for an allowed plan.
        AiEngineSettings::setEnabled(false);
        $allowed = $this->makeUser($this->plan());
        $this->actingAs($allowed)
            ->postJson('/user/brand-studio/estimate', ['request' => 'Launch our summer sale.'])
            ->assertStatus(404);

        // Engine on but the plan disables brand_studio → 403.
        AiEngineSettings::setEnabled(true);
        $blocked = $this->makeUser($this->plan(['brand_studio' => false]));
        $this->actingAs($blocked)
            ->postJson('/user/brand-studio/estimate', ['request' => 'Launch our summer sale.'])
            ->assertStatus(403);
    }

    public function test_api_estimate_matches_service_and_returns_balance(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser($this->plan());
        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->postJson('/api/v1/brand-studio/estimate', [
            'request' => 'Launch our summer sale.',
        ]);

        $resp->assertOk();
        $expected = $this->expectedEstimate($user, 'Launch our summer sale.');
        $this->assertGreaterThan(0, $expected);
        $this->assertSame($expected, $resp->json('data.estimated_credits'));
        $this->assertIsInt($resp->json('data.balance'));
        $this->flushHeaders();
    }

    public function test_api_estimate_bulk_mode_matches_service(): void
    {
        AiEngineSettings::setEnabled(true);
        $user = $this->makeUser($this->plan());
        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->postJson('/api/v1/brand-studio/estimate', [
            'request'    => 'Promo links for our summer sale.',
            'mode'       => 'bulk',
            'bulk_kind'  => 'short_link',
            'bulk_count' => 10,
        ]);

        $resp->assertOk();
        $expected = $this->expectedEstimate($user, 'Promo links for our summer sale.', 'bulk', 'short_link', 10);
        $this->assertSame($expected, $resp->json('data.estimated_credits'));
        $this->assertIsInt($resp->json('data.balance'));
        $this->flushHeaders();
    }

    public function test_api_estimate_gated_by_engine_and_plan_flags(): void
    {
        // Engine off → 404 with the error envelope.
        AiEngineSettings::setEnabled(false);
        $allowed = $this->makeUser($this->plan());
        $this->withToken($allowed->createToken('test')->plainTextToken);
        $off = $this->postJson('/api/v1/brand-studio/estimate', ['request' => 'Launch our summer sale.']);
        $off->assertStatus(404);
        $this->assertSame('ai_disabled', $off->json('error.code'));
        $this->flushHeaders();

        // Engine on, plan disables brand_studio → plan-gate envelope.
        AiEngineSettings::setEnabled(true);
        $blocked = $this->makeUser($this->plan(['brand_studio' => false]));
        $this->withToken($blocked->createToken('test')->plainTextToken);
        $gated = $this->postJson('/api/v1/brand-studio/estimate', ['request' => 'Launch our summer sale.']);
        $gated->assertStatus(402);
        $this->assertSame('plan_upgrade_required', $gated->json('error.code'));
        $this->flushHeaders();
    }

    // ── 7. discarding a proposal refunds + cleans up (Task #5576) ──────
    //
    // Discarding an unconfirmed proposal must delete the kit AND refund
    // the exact planning charge (through the real wallet ledger, with an
    // idempotency key so a double discard can never credit twice). A kit
    // that was already materialized deletes WITHOUT a refund.

    private function seedProposalKit(User $user, int $credits = 7): BrandStudioKit
    {
        return BrandStudioKit::create([
            'user_id'       => $user->id,
            'name'          => 'Discard Test Kit',
            'mode'          => BrandStudioKit::MODE_KIT,
            'status'        => BrandStudioKit::STATUS_PROPOSAL,
            'request'       => 'Launch our summer sale.',
            'brand'         => [],
            'proposal'      => ['assets' => [['kind' => 'short_link', 'title' => 'Shop', 'url' => 'https://example.test/shop']]],
            'credits_spent' => $credits,
        ]);
    }

    public function test_web_discard_of_proposal_refunds_credits_and_deletes_kit(): void
    {
        $user = $this->makeUser($this->plan());
        $kit  = $this->seedProposalKit($user, 7);

        $charger = app(AiUsageCharger::class);
        $before  = $charger->getBalance($user);

        $resp = $this->actingAs($user)->delete('/user/brand-studio/' . $kit->id);

        $resp->assertRedirect(route('user.brand-studio.index'));
        $resp->assertSessionHas('status', 'Plan discarded — 7 credits refunded.');
        $this->assertDatabaseMissing('brand_studio_kits', ['id' => $kit->id]);
        $this->assertSame($before + 7, $charger->getBalance($user->fresh()));

        // The refund is a real ledger row with the discard idempotency key.
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id'         => $user->id,
            'type'            => 'refund',
            'delta_coins'     => 7,
            'idempotency_key' => 'brand_studio_discard_' . $kit->id,
        ]);
    }

    public function test_discard_is_idempotent_and_created_kits_do_not_refund(): void
    {
        $user = $this->makeUser($this->plan());

        // Double discard of the same proposal: only one refund lands.
        $kit = $this->seedProposalKit($user, 5);
        $svc = $this->service();
        $this->assertSame(5, $svc->discard($kit));
        $this->assertSame(0, $svc->discard($kit), 'second discard must be a no-op');
        $charger = app(AiUsageCharger::class);
        $this->assertSame(5, $charger->getBalance($user->fresh()));

        // A materialized (created) kit deletes without any refund.
        $created = $this->seedProposalKit($user, 9);
        $created->update(['status' => BrandStudioKit::STATUS_CREATED]);
        $this->assertSame(0, $svc->discard($created->fresh()));
        $this->assertSame(5, $charger->getBalance($user->fresh()));
        $this->assertDatabaseMissing('brand_studio_kits', ['id' => $created->id]);
    }

    public function test_api_discard_refunds_and_reports_refunded_credits(): void
    {
        $user = $this->makeUser($this->plan());
        $kit  = $this->seedProposalKit($user, 4);
        $this->withToken($user->createToken('test')->plainTextToken);

        $resp = $this->deleteJson('/api/v1/brand-studio/' . $kit->id);

        $resp->assertOk();
        $this->assertTrue($resp->json('data.deleted'));
        $this->assertSame(4, $resp->json('data.refunded_credits'));
        $this->assertDatabaseMissing('brand_studio_kits', ['id' => $kit->id]);
        $this->assertSame(4, app(AiUsageCharger::class)->getBalance($user->fresh()));
        $this->flushHeaders();
    }

    public function test_discard_is_forbidden_for_other_users(): void
    {
        $owner    = $this->makeUser($this->plan());
        $stranger = $this->makeUser($this->plan());
        $kit      = $this->seedProposalKit($owner, 6);

        $this->actingAs($stranger)->delete('/user/brand-studio/' . $kit->id)->assertStatus(403);
        $this->assertDatabaseHas('brand_studio_kits', ['id' => $kit->id]);
        $this->assertSame(0, app(AiUsageCharger::class)->getBalance($owner->fresh()));
    }
}
