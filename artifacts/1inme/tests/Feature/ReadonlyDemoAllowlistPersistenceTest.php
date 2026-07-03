<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingService;
use App\Modules\User\Models\SubscriptionPromoCode;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\OpenAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

/**
 * Task #3511 — behavioural coverage that the *allowlisted* interactive routes
 * ({@see \App\Modules\Common\Middleware\BlockReadonlyDemoWrites::ALLOWED_INTERACTIVE_ROUTE_NAMES}
 * and ::ALLOWED_INTERACTIVE_PATHS}) genuinely persist NOTHING when a read-only
 * demo account hits them.
 *
 * Why this exists on top of the existing guards:
 *   - `demo:check-allowlist` (the drift guard) only proves each interactive
 *     write route is *classified* — it never runs the controller, so the
 *     "this route saves nothing" judgement is a hand read of the code.
 *   - {@see ReadonlyDemoWriteGuardTest} proves the middleware blocks a real
 *     save and lets one allowlisted route (QR download) through — but it does
 *     not assert, table by table, that the allowlisted routes write nothing.
 *
 * The gap: a controller behind an interactive-looking name (`.estimate`,
 * `.preview*`, `quote`, …) could later gain a side-effecting write while
 * keeping its name, and the drift guard would still pass. These tests close
 * that gap for a representative sample by driving each route as a logged-in
 * read-only demo user and asserting a FULL-database row-count snapshot is
 * unchanged across the request (so a write to ANY table, not just the obvious
 * one, fails the test).
 *
 * Runs in CI (DB-backed). The repo's shared-RDS test-DB guard
 * ({@see TestCase::setUp}) means it can't be run locally, so it mirrors the
 * login/setup pattern of {@see ReadonlyDemoWriteGuardTest}.
 */
class ReadonlyDemoAllowlistPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private const BLOCK_MESSAGE = "This is a demo — changes aren't saved.";

    /**
     * Infrastructure tables whose row count legitimately moves on any request
     * (auth session, token bookkeeping, queue, cache when DB-backed). They are
     * never the "did the demo save something?" signal, so they're excluded from
     * the persistence snapshot. Everything else — every business table — must
     * be byte-for-byte unchanged across an allowlisted interactive request.
     */
    private const INFRA_TABLES = [
        'migrations',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'password_resets',
        'password_reset_tokens',
        'personal_access_tokens',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // Auth/login + editor views use @vite; swap it for a no-op so any
        // rendered page works without a built manifest in the test env.
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeDemoUser(?Plan $plan = null): User
    {
        $user = User::create([
            'name'              => 'Demo ' . Str::random(4),
            'email'             => 'demo' . Str::random(8) . '@ex.com',
            'password'          => Hash::make('secret-pass'),
            'status'            => 'active',
            'email_verified_at' => now(),
            'onboarded_at'      => now(),
            'is_readonly_demo'  => true,
            // The AI-*estimate* routes gate on a paid plan / plan feature flags;
            // callers that exercise those pass a plan built by paidAiPlan().
            'plan_id'           => $plan?->id,
        ]);
        // Owns a personal workspace so `workspace.can:*` permission gates on the
        // interactive routes pass (owner status grants every permission).
        $user->ensureDefaultWorkspace();

        return $user->fresh();
    }

    /** The demo user's default (personal) workspace id. */
    private function wsId(User $user): int
    {
        return $user->ensureDefaultWorkspace()->id;
    }

    /**
     * A non-free plan whose feature flags unlock every AI surface reached by
     * the allowlisted `.estimate` routes (marketing strategist, brand kit,
     * biolink builder, resume tools). Estimate endpoints gate on these; without
     * a paid plan they 403/gate-fail and the "persists nothing" assertion would
     * pass for the wrong reason (never reaching the controller body).
     */
    private function paidAiPlan(): Plan
    {
        return Plan::create([
            'name'          => 'Growth ' . Str::random(4),
            'slug'          => 'growth-' . Str::lower(Str::random(8)),
            'monthly_price' => 19,
            'annual_price'  => 190,
            'status'        => 'active',
            'sort_order'    => 50,
            'features'      => [
                'max_links'                => 100,
                'max_biolinks'             => 100,
                'max_brand_kits'           => 10,
                'max_marketing_strategies' => 10,
                'marketing_strategist'     => true,
                'brand_consistency'        => true,
                'ai_resume_tools'          => true,
            ],
        ]);
    }

    /**
     * Swap the real OpenAI client for a stub so the AI-*generation* previews
     * (coach.suggest / mind.think) never make a network call or charge coins —
     * their only allowed-to-run behaviour is rendering a flashed result and
     * redirecting. The estimate routes are pure arithmetic and don't need this.
     */
    private function mockOpenAi(): void
    {
        $mock = Mockery::mock(OpenAiService::class);
        $mock->shouldReceive('chat')->andReturn([
            'content'       => "Summary of the situation.\n- Do X\n- Do Y\n- Do Z",
            'tool_calls'    => [],
            'finish_reason' => 'stop',
            'tokens_in'     => 0,
            'tokens_out'    => 0,
            'credits_spent' => 0,
            'model'         => 'gpt-4o-mini',
            'raw'           => [],
        ]);
        $this->app->instance(OpenAiService::class, $mock);
    }

    /**
     * Drive the real web OTP login flow (send-otp → verify-otp). In non-prod
     * the code is the fixed `123456`. Mirrors production, where password login
     * is disabled for the demo account, leaving OTP as the only way in.
     */
    private function loginViaOtp(User $user): void
    {
        $this->post('/user/send-otp', [
            'identifier' => $user->email,
            'type'       => 'email',
        ])->assertRedirect(route('user.otp.verify.form'));

        $this->post('/user/verify-otp', ['code' => '123456']);

        $this->assertAuthenticatedAs($user->fresh());
    }

    /**
     * Snapshot the row count of every business (non-infrastructure) table in
     * the public schema. Counting all tables — instead of just the obvious
     * one — is the whole point: it catches a NEW side-effecting write to ANY
     * table that a future controller change might introduce behind an
     * interactive-looking route name.
     *
     * @return array<string,int> table name => row count
     */
    private function businessRowCounts(): array
    {
        $tables = DB::select(
            "SELECT table_name FROM information_schema.tables "
            . "WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"
        );

        $counts = [];
        foreach ($tables as $row) {
            $name = $row->table_name;
            if (in_array($name, self::INFRA_TABLES, true)) {
                continue;
            }
            $counts[$name] = (int) DB::table($name)->count();
        }
        ksort($counts);

        return $counts;
    }

    /**
     * Run $act (which performs the request) and assert it changed no business
     * table's row count. Returns the request's response so the caller can also
     * assert its HTTP status/shape.
     */
    private function assertPersistsNothing(callable $act, string $label): TestResponse
    {
        $before   = $this->businessRowCounts();
        $response = $act();
        $after    = $this->businessRowCounts();

        $changed = [];
        foreach ($after as $table => $count) {
            $prev = $before[$table] ?? 0;
            if ($count !== $prev) {
                $changed[$table] = "{$prev} -> {$count}";
            }
        }

        $this->assertSame(
            [],
            $changed,
            "{$label} is allowlisted as interactive/non-persisting but wrote rows to: "
            . json_encode($changed)
        );

        return $response;
    }

    /**
     * `.estimate` sample — user.ai.cost-estimate (POST /user/ai/cost-estimate).
     * The unified AI coin-cost estimate: pure arithmetic dry-run, no AI call,
     * no charge, no rows.
     */
    public function test_allowlisted_ai_cost_estimate_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();
        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson('/user/ai/cost-estimate', ['feature' => 'ask_coach']),
            'user.ai.cost-estimate'
        );

        $response->assertOk()
            ->assertJsonStructure(['coins', 'mode', 'balance', 'low'])
            ->assertSessionHasNoErrors();
    }

    /**
     * `.preview*` sample — user.links.preview-draft
     * (POST /user/links/{link}/preview-draft). Stashes an unsaved editor draft
     * into the cache (array driver in tests) with a 10-minute TTL; it must not
     * touch the link, its blocks, or any other table.
     */
    public function test_allowlisted_preview_draft_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();

        $link = Link::create([
            'user_id'   => $demo->id,
            'type'      => Link::TYPE_BIOLINK,
            'alias'     => Link::generateAlias(),
            'title'     => 'Demo Biolink',
            'is_active' => true,
        ]);

        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson("/user/links/{$link->id}/preview-draft", [
                'biolink_title'    => 'Draft title that should never be saved',
                'background_color' => '#123456',
            ]),
            'user.links.preview-draft'
        );

        $response->assertOk()->assertJson(['success' => true]);
    }

    /**
     * `quote` sample — rm.public.quote (POST /rm/{alias}/quote). Computes a
     * live estimated bill for a guest's cart; explicitly creates no order. The
     * demo user is logged in so the guard runs and honours the allowlist.
     */
    public function test_allowlisted_restaurant_quote_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();

        $link = Link::create([
            'user_id'   => $demo->id,
            'type'      => Link::TYPE_RESTAURANT_MENU,
            'alias'     => Link::generateAlias(),
            'title'     => 'Cafe Bistro',
            'is_active' => true,
        ]);
        $menu = RestaurantMenu::create([
            'link_id'  => $link->id,
            'user_id'  => $demo->id,
            'mode'     => RestaurantMenu::MODE_ORDER,
            'currency' => 'USD',
            'settings' => ['tax' => ['enabled' => true, 'rate' => 10, 'inclusive' => false]],
        ]);
        $item = RestaurantMenuItem::create([
            'menu_id'   => $menu->id,
            'name'      => 'Espresso',
            'price'     => 4.50,
            'is_active' => true,
        ]);

        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson("/rm/{$link->alias}/quote", [
                'items' => [['item_id' => $item->id, 'quantity' => 2]],
            ]),
            'rm.public.quote'
        );

        $response->assertOk()->assertJsonPath('data.bill.is_estimate', true);
    }

    /**
     * Interactive-image sample — user.qrcode.download (POST /user/qrcode).
     * Renders a standalone QR image only; no library row, no charge. (The
     * existing guard test asserts the SVG response; here we additionally pin
     * that the render writes nothing.)
     */
    public function test_allowlisted_standalone_qr_download_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();
        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->post('/user/qrcode', [
                'url'    => 'https://example.com/demo-qr',
                'format' => 'svg',
            ]),
            'user.qrcode.download'
        );

        $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    /**
     * `quote` sample — sb.public.quote (POST /sb/{alias}/quote). Prices a
     * service-booking cart into an estimated duration + bill; explicitly
     * creates no booking. Demo logged in so the guard honours the allowlist.
     */
    public function test_allowlisted_service_booking_quote_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();

        $link = Link::create([
            'user_id'   => $demo->id,
            'type'      => 'service_booking',
            'alias'     => Link::generateAlias(),
            'title'     => 'Consulting',
            'is_active' => true,
        ]);
        $config = ServiceBooking::create([
            'link_id'  => $link->id,
            'user_id'  => $demo->id,
            'mode'     => ServiceBooking::MODE_BOOKING,
            'currency' => 'USD',
        ]);
        $service = ServiceBookingService::create([
            'service_booking_id' => $config->id,
            'name'               => 'Consultation',
            'price'              => 50.00,
            'currency'           => 'USD',
            'duration_minutes'   => 30,
            'is_active'          => true,
            'is_unavailable'     => false,
        ]);

        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson("/sb/{$link->alias}/quote", [
                'services' => [['service_id' => $service->id, 'quantity' => 2]],
            ]),
            'sb.public.quote'
        );

        $response->assertOk()->assertJsonPath('data.duration_minutes', 60);
    }

    /**
     * `.preview-promo` sample — creator-profile.subscribe.preview-promo
     * (POST /@{handle}/subscribe/preview-promo). Applies a promo code to a
     * subscription tier to preview the discounted price; validates + computes,
     * never records a redemption or subscription.
     */
    public function test_allowlisted_subscribe_preview_promo_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();
        $demo->forceFill(['handle' => 'creator' . Str::lower(Str::random(8))])->save();
        $demo->refresh();

        $tier = SubscriptionTier::create([
            'user_id'             => $demo->id,
            'name'                => 'Pro Tier',
            'slug'                => SubscriptionTier::makeSlug($demo->id, 'Pro Tier'),
            'is_active'           => true,
            'is_free'             => false,
            'price_monthly_cents' => 1000,
            'currency'            => 'USD',
        ]);
        SubscriptionPromoCode::create([
            'user_id'             => $demo->id,
            'code'                => 'SAVE50',
            'kind'                => SubscriptionPromoCode::KIND_PERCENT,
            'value'               => 50,
            'is_active'           => true,
            'applies_to_tier_ids' => [],
            'redemptions_count'   => 0,
        ]);

        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson(
                route('creator-profile.subscribe.preview-promo', ['handle' => $demo->handle]),
                ['tier_id' => $tier->id, 'cycle' => 'monthly', 'promo_code' => 'SAVE50']
            ),
            'creator-profile.subscribe.preview-promo'
        );

        $response->assertOk()->assertJson(['ok' => true, 'final_cents' => 500]);
    }

    /**
     * `.preview` sample — user.billing.companies.emails.preview
     * (POST /user/billing/companies/{company}/emails/{key}/preview). Renders a
     * live email-template preview from a draft; touches no company_email_templates
     * row (that's the separate save endpoint).
     */
    public function test_allowlisted_company_email_preview_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();

        $company = BillingCompany::create([
            'user_id'      => $demo->id,
            'workspace_id' => $this->wsId($demo),
            'name'         => 'Acme Corp',
        ]);

        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson(
                route('user.billing.companies.emails.preview', [
                    'company' => $company->id,
                    'key'     => 'billing.client_invoice',
                ]),
                ['subject' => 'Your invoice', 'body' => 'Hello {{client_name}}', 'format' => 'html']
            ),
            'user.billing.companies.emails.preview'
        );

        $response->assertOk()->assertJsonStructure(['subject', 'body', 'format']);
    }

    /**
     * Bulk dry-run sample — user.links.url.bulk.preview
     * (POST /user/links/url/bulk/preview). Parses + validates pasted URLs and
     * renders a preview screen; the real create is a separate submit. No links
     * (or anything else) are written.
     */
    public function test_allowlisted_bulk_url_preview_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();
        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->post(route('user.links.url.bulk.preview'), [
                'urls_text' => "https://example.com/one\nhttps://example.com/two",
            ]),
            'user.links.url.bulk.preview'
        );

        $response->assertOk();
    }

    /**
     * Bulk dry-run sample — user.links.biolink.bulk.preview
     * (POST /user/links/biolink/bulk/preview). Mail-merges a sheet against a
     * blueprint biolink page and renders a preview; the real generation is a
     * separate submit. Nothing — not the pages, not the sheet — is persisted.
     */
    public function test_allowlisted_bulk_biolink_preview_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();

        $blueprint = Link::create([
            'user_id'      => $demo->id,
            'workspace_id' => $this->wsId($demo),
            'type'         => Link::TYPE_BIOLINK,
            'alias'        => Link::generateAlias(),
            'title'        => 'Blueprint Page',
            'is_active'    => true,
        ]);
        BiolinkBlock::create([
            'link_id'    => $blueprint->id,
            'type'       => 'paragraph',
            'sort_order' => 0,
            'is_active'  => true,
            'settings'   => ['text' => 'Welcome {{name}}'],
        ]);

        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->post(route('user.links.biolink.bulk.preview'), [
                'source'     => 'page',
                'link_id'    => $blueprint->id,
                'sheet_text' => "name\nAlice",
            ]),
            'user.links.biolink.bulk.preview'
        );

        $response->assertOk();
    }

    /**
     * Interactive-image sample — user.links.qrcode.download
     * (POST /user/links/{link}/qrcode). Renders a specific link's QR image on
     * demand; no library row, no charge. Sister of the standalone QR route.
     */
    public function test_allowlisted_per_link_qr_download_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();

        $link = Link::create([
            'user_id'      => $demo->id,
            'workspace_id' => $this->wsId($demo),
            'type'         => Link::TYPE_BIOLINK,
            'alias'        => Link::generateAlias(),
            'title'        => 'Demo Biolink',
            'is_active'    => true,
        ]);

        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->post(route('user.links.qrcode.download', ['link' => $link->id]), [
                'format' => 'svg',
            ]),
            'user.links.qrcode.download'
        );

        $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    /**
     * AI credit-cost `.estimate` sample — user.ai.marketing-strategist.estimate.
     * Arithmetic-only dry run of the coin cost; no OpenAI call, no charge. The
     * engine must be ON (estimate aborts 404 when disabled) and the plan must
     * unlock the feature, so we reach the estimate body — but it still writes
     * nothing.
     */
    public function test_allowlisted_marketing_strategist_estimate_persists_nothing(): void
    {
        AiEngineSettings::setEnabled(true);
        $demo = $this->makeDemoUser($this->paidAiPlan());
        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson(route('user.ai.marketing-strategist.estimate'), [
                'goal'    => 'Grow my newsletter audience this quarter',
                'sources' => ['links'],
            ]),
            'user.ai.marketing-strategist.estimate'
        );

        $response->assertOk()->assertJsonStructure(['estimate', 'balance']);
    }

    /**
     * AI credit-cost `.estimate` sample — user.brand-kits.estimate. Arithmetic
     * dry run only; engine ON so it doesn't 404, plan unlocks brand kits. Writes
     * nothing.
     */
    public function test_allowlisted_brand_kit_estimate_persists_nothing(): void
    {
        AiEngineSettings::setEnabled(true);
        $demo = $this->makeDemoUser($this->paidAiPlan());
        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson(route('user.brand-kits.estimate'), [
                'prompt' => 'A modern, minimal tech brand with a blue palette',
            ]),
            'user.brand-kits.estimate'
        );

        $response->assertOk()->assertJsonStructure(['estimated_credits', 'balance']);
    }

    /**
     * AI credit-cost `.estimate` sample — user.links.ai-builder.estimate.
     * Arithmetic dry run of the AI biolink builder cost for an owned link;
     * engine ON, no charge, no rows.
     */
    public function test_allowlisted_ai_builder_estimate_persists_nothing(): void
    {
        AiEngineSettings::setEnabled(true);
        $demo = $this->makeDemoUser($this->paidAiPlan());

        $link = Link::create([
            'user_id'      => $demo->id,
            'workspace_id' => $this->wsId($demo),
            'type'         => Link::TYPE_BIOLINK,
            'alias'        => Link::generateAlias(),
            'title'        => 'Demo Biolink',
            'is_active'    => true,
        ]);

        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson(route('user.links.ai-builder.estimate', ['link' => $link->id]), [
                'description'   => 'Build me a link-in-bio page for my coffee shop with menu and hours.',
                'use_brand_kit' => false,
            ]),
            'user.links.ai-builder.estimate'
        );

        $response->assertOk()->assertJsonStructure(['estimated_credits', 'balance']);
    }

    /**
     * AI credit-cost `.estimate` sample — user.resume.tailor.estimate.
     * Arithmetic dry run for tailoring the demo user's resume to a JD; engine
     * ON, resume pre-created so the request's ensureResume() is a no-op. No
     * charge, no rows.
     */
    public function test_allowlisted_resume_tailor_estimate_persists_nothing(): void
    {
        AiEngineSettings::setEnabled(true);
        $demo = $this->makeDemoUser($this->paidAiPlan());
        $demo->ensureResume();
        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson(route('user.resume.tailor.estimate'), [
                'job_description' => str_repeat('Senior product manager role with a growth focus. ', 3),
            ]),
            'user.resume.tailor.estimate'
        );

        $response->assertOk()->assertJsonStructure(['estimated_credits', 'balance']);
    }

    /**
     * AI credit-cost `.estimate` sample — user.resume.cover-letters.estimate.
     * Arithmetic dry run for a cover letter against a JD; engine ON, resume
     * pre-created. No charge, no rows.
     */
    public function test_allowlisted_resume_cover_letter_estimate_persists_nothing(): void
    {
        AiEngineSettings::setEnabled(true);
        $demo = $this->makeDemoUser($this->paidAiPlan());
        $demo->ensureResume();
        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->postJson(route('user.resume.cover-letters.estimate'), [
                'job_description' => str_repeat('Senior product manager role with a growth focus. ', 3),
                'tone'            => 'professional',
            ]),
            'user.resume.cover-letters.estimate'
        );

        $response->assertOk()->assertJsonStructure(['estimated_credits', 'balance']);
    }

    /**
     * AI-*generation* preview that IS allowlisted — user.ai.coach.suggest
     * (POST /user/ai/coach/suggest). Unlike the estimate routes this DOES call
     * the model, so we stub OpenAiService (no network, no charge). Its only
     * allowed side effect is flashing a result and redirecting — no rows.
     */
    public function test_allowlisted_ai_coach_suggest_persists_nothing(): void
    {
        AiEngineSettings::setEnabled(true);
        $this->mockOpenAi();

        $demo = $this->makeDemoUser($this->paidAiPlan());
        $link = Link::create([
            'user_id'      => $demo->id,
            'workspace_id' => $this->wsId($demo),
            'type'         => Link::TYPE_BIOLINK,
            'alias'        => Link::generateAlias(),
            'title'        => 'Demo Biolink',
            'is_active'    => true,
        ]);

        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->post(route('user.ai.coach.suggest'), [
                'link_id' => $link->id,
                'goal'    => 'increase engagement',
            ]),
            'user.ai.coach.suggest'
        );

        $response->assertRedirect(route('user.ai.coach.show'));
    }

    /**
     * AI-*generation* preview that IS allowlisted — user.ai.mind.think
     * (POST /user/ai/mind/think). Stub OpenAiService so no network/charge; its
     * only allowed side effect is flashing the result and redirecting. No rows.
     */
    public function test_allowlisted_ai_mind_think_persists_nothing(): void
    {
        AiEngineSettings::setEnabled(true);
        $this->mockOpenAi();

        $demo = $this->makeDemoUser($this->paidAiPlan());
        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->post(route('user.ai.mind.think'), [
                'thoughts' => 'I need to plan my launch week and prioritize the biggest tasks.',
            ]),
            'user.ai.mind.think'
        );

        $response->assertRedirect(route('user.ai.mind.show'));
    }

    /**
     * Negative control: a route that is NOT on the interactive allowlist
     * (`user.qr-codes.generate-art`, acknowledged as persisting/charging) must
     * still be blocked with the demo flash AND persist nothing — proving the
     * "persists nothing" here comes from the guard short-circuit, not from the
     * controller quietly being safe.
     */
    public function test_non_allowlisted_generate_art_is_blocked_and_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();
        $this->loginViaOtp($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->post('/user/qr-codes/generate-art', ['prompt' => 'a neon fox']),
            'user.qr-codes.generate-art (blocked)'
        );

        $response->assertRedirect()->assertSessionHas('error', self::BLOCK_MESSAGE);
    }
}
