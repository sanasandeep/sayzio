<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingAvailabilityRule;
use App\Modules\User\Models\ServiceBookingService;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Task #3516 — behavioural coverage that the *allowlisted* interactive
 * Sanctum API path patterns
 * ({@see \App\Modules\Common\Middleware\BlockReadonlyDemoWrites::ALLOWED_INTERACTIVE_PATHS})
 * genuinely persist NOTHING when a read-only demo account hits them over the
 * REST/mobile bearer-token surface.
 *
 * This is the API/mobile parity to {@see ReadonlyDemoAllowlistPersistenceTest},
 * which only exercised the *web* (session) routes. The parallel API allowlist
 * is matched by URI pattern (routes/api.php largely doesn't name its routes),
 * so a demo mobile client TRYING a showcase feature — a merge preview, a
 * price quote, an AI cost estimate, an email-template render — flows through a
 * different allowlist branch (`ALLOWED_INTERACTIVE_PATHS`) than the web one.
 *
 * The gap this closes: `demo:check-allowlist` only proves each interactive
 * write path is *classified*; it never runs the controller. A controller
 * behind an interactive-looking URI (`/estimate`, `/preview`, `/quote`, …)
 * could later gain a side-effecting write while keeping its path, and the
 * drift guard would still pass. Each test here drives the real route as a
 * logged-in read-only demo user (with a genuine personal access token) and
 * asserts a FULL-database row-count snapshot is unchanged across the request,
 * so a write to ANY table — not just the obvious one — fails the test.
 *
 * Authenticated requests use a real Sanctum token, NOT `Sanctum::actingAs`:
 * that injects a Mockery mock the TouchSessionToken middleware can't
 * `->save()`, so every authed request would 500 (see the "Sanctum API
 * feature tests" convention).
 *
 * Runs in CI (DB-backed); the shared-RDS test-DB guard ({@see TestCase::setUp})
 * means it can't be run locally.
 */
class ReadonlyDemoAllowlistApiPersistenceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Infrastructure tables whose row count legitimately moves on any request
     * (auth/token bookkeeping, queue, cache when DB-backed). They are never the
     * "did the demo save something?" signal, so they're excluded from the
     * persistence snapshot. Everything else — every business table — must be
     * byte-for-byte unchanged across an allowlisted interactive request.
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
        // The AI cost-estimate paths short-circuit to 404/503 when the engine
        // is off; enabling it lets the real (still non-persisting, non-charging)
        // estimate arithmetic run so the snapshot assertion is meaningful.
        AiEngineSettings::setEnabled(true);
    }

    /**
     * A read-only demo user on a PAID plan (so the legacy AI-feature gate —
     * `!isOnFreePlan()` — lets the Marketing Strategist estimate through) that
     * owns a personal workspace (so `workspace.can:*` gates pass).
     */
    private function makeDemoUser(): User
    {
        $plan = Plan::create([
            'name'          => 'Growth',
            'slug'          => 'growth-' . Str::random(6),
            'monthly_price' => 19,
            'annual_price'  => 190,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 5,
            'features'      => [
                'max_links'    => 100,
                'max_biolinks' => 100,
            ],
        ]);

        $user = User::create([
            'name'              => 'Demo ' . Str::random(4),
            'email'             => 'demo' . Str::random(8) . '@ex.com',
            'password'          => Hash::make('secret-pass'),
            'status'            => 'active',
            'email_verified_at' => now(),
            'onboarded_at'      => now(),
            'plan_id'           => $plan->id,
            'is_readonly_demo'  => true,
        ]);
        $user->ensureDefaultWorkspace();

        return $user->fresh();
    }

    /** Mint a real personal access token (see the sanctum-api-tests convention). */
    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * Snapshot the row count of every business (non-infrastructure) table in
     * the public schema. Counting all tables — instead of just the obvious one
     * — is the whole point: it catches a NEW side-effecting write to ANY table
     * a future controller change might introduce behind an interactive-looking
     * API path.
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
     * table's row count. Returns the response so the caller can also assert
     * status/shape.
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

        // The response must NOT be the demo write-block — that would mean the
        // route was blocked, not exercised, and "persists nothing" would be a
        // false positive from the guard short-circuit rather than from the
        // controller genuinely saving nothing.
        $this->assertNotSame(
            'demo_readonly',
            $response->json('error.code'),
            "{$label} was blocked by the demo guard instead of being allowlisted."
        );

        return $response;
    }

    /**
     * `account/merge/preview` — rebuilds the merge preview (a pure count read)
     * from a still-valid, APP_KEY-encrypted merge token. Creates no rows.
     */
    public function test_api_account_merge_preview_persists_nothing(): void
    {
        $demo      = $this->makeDemoUser();
        $secondary = User::create([
            'name'     => 'Other ' . Str::random(4),
            'email'    => 'other' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);

        // Mint the token exactly as the controller does so the preview step
        // resolves without walking the challenge/verify OTP dance.
        $mergeToken = Crypt::encryptString((string) json_encode([
            'p'   => $demo->id,
            's'   => $secondary->id,
            'exp' => now()->addMinutes(15)->getTimestamp(),
        ]));

        $token = $this->token($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->withToken($token)->postJson('/api/v1/account/merge/preview', [
                'merge_token' => $mergeToken,
            ]),
            'api/v1/account/merge/preview'
        );

        $response->assertOk()->assertJsonPath('data.preview.primary.email', $demo->email);
    }

    /**
     * `ai/marketing-strategist/estimate` — pure credit-cost arithmetic
     * dry-run (no AI call, no charge, no rows).
     */
    public function test_api_marketing_strategist_estimate_persists_nothing(): void
    {
        $demo  = $this->makeDemoUser();
        $token = $this->token($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->withToken($token)->postJson('/api/v1/ai/marketing-strategist/estimate', [
                'goal' => 'Grow my newsletter audience over the next quarter.',
            ]),
            'api/v1/ai/marketing-strategist/estimate'
        );

        $response->assertOk()->assertJsonStructure(['data' => ['estimate', 'balance']]);
    }

    /**
     * `brand-kits/estimate` — AI credit-cost estimate; no charge, no rows.
     */
    public function test_api_brand_kits_estimate_persists_nothing(): void
    {
        $demo  = $this->makeDemoUser();
        $token = $this->token($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->withToken($token)->postJson('/api/v1/brand-kits/estimate', [
                'prompt' => 'A calm, minimal wellness brand in sage and cream.',
            ]),
            'api/v1/brand-kits/estimate'
        );

        $response->assertOk()->assertJsonStructure(['data' => ['estimated_credits', 'balance']]);
    }

    /**
     * `billing/companies/{id}/emails/{key}/preview` — renders an email-template
     * preview for a company the demo user owns; a pure render, no rows.
     */
    public function test_api_company_email_template_preview_persists_nothing(): void
    {
        $demo    = $this->makeDemoUser();
        $company = BillingCompany::create([
            'user_id' => $demo->id,
            'name'    => 'Acme ' . Str::random(4),
            'email'   => 'biz' . Str::random(4) . '@ex.com',
        ]);

        $token = $this->token($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->withToken($token)->postJson(
                "/api/v1/billing/companies/{$company->id}/emails/billing.receipt/preview",
                [
                    'subject' => 'Draft subject that must never be saved',
                    'body'    => 'Draft body that must never be saved',
                    'format'  => 'html',
                ]
            ),
            'api/v1/billing/companies/*/emails/*/preview'
        );

        $response->assertOk();
    }

    /**
     * `links/{id}/ai-builder/estimate` — AI credit-cost estimate for the
     * demo user's own biolink; no charge, no rows.
     */
    public function test_api_ai_builder_estimate_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();
        $link = Link::create([
            'user_id'   => $demo->id,
            'type'      => Link::TYPE_BIOLINK,
            'alias'     => Link::generateAlias(),
            'title'     => 'Demo Biolink',
            'is_active' => true,
        ]);

        $token = $this->token($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->withToken($token)->postJson("/api/v1/links/{$link->id}/ai-builder/estimate", [
                'description' => 'A friendly link-in-bio page for a coffee roaster with menu and socials.',
            ]),
            'api/v1/links/*/ai-builder/estimate'
        );

        $response->assertOk()->assertJsonStructure(['data' => ['estimated_credits', 'balance']]);
    }

    /**
     * `restaurant/{alias}/quote` — computes a live estimated bill for a guest's
     * cart; explicitly creates no order.
     */
    public function test_api_restaurant_quote_persists_nothing(): void
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
        $category = \App\Modules\User\Models\RestaurantMenuCategory::create([
            'menu_id'   => $menu->id,
            'name'      => 'Drinks',
            'is_active' => true,
        ]);
        $item = RestaurantMenuItem::create([
            'menu_id'     => $menu->id,
            'category_id' => $category->id,
            'name'      => 'Espresso',
            'price'     => 4.50,
            'is_active' => true,
        ]);

        $token = $this->token($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->withToken($token)->postJson("/api/v1/restaurant/{$link->alias}/quote", [
                'items' => [['item_id' => $item->id, 'quantity' => 2]],
            ]),
            'api/v1/restaurant/*/quote'
        );

        $response->assertOk()->assertJsonPath('data.bill.is_estimate', true);
    }

    /**
     * `service-booking/{alias}/quote` — prices a service cart into an estimated
     * bill; creates no booking request.
     */
    public function test_api_service_booking_quote_persists_nothing(): void
    {
        $demo = $this->makeDemoUser();
        $link = Link::create([
            'user_id'   => $demo->id,
            'type'      => Link::TYPE_SERVICE_BOOKING,
            'alias'     => Link::generateAlias(),
            'title'     => 'Studio Sessions',
            'is_active' => true,
        ]);
        $booking = ServiceBooking::create([
            'link_id'             => $link->id,
            'user_id'             => $demo->id,
            'mode'                => ServiceBooking::MODE_BOOKING,
            'currency'            => 'USD',
            'slot_length_minutes' => 30,
            'lead_time_minutes'   => 0,
            'max_days_ahead'      => 30,
            'timezone'            => 'UTC',
            'settings'            => ['tax' => ['enabled' => true, 'rate' => 10, 'inclusive' => false]],
        ]);
        ServiceBookingAvailabilityRule::create([
            'service_booking_id' => $booking->id,
            'day_of_week'        => 3,
            'start_time'         => '09:00',
            'end_time'           => '17:00',
            'is_active'          => true,
        ]);
        $service = ServiceBookingService::create([
            'service_booking_id' => $booking->id,
            'name'               => 'Haircut',
            'price'              => 50.0,
            'duration_minutes'   => 30,
            'is_active'          => true,
            'is_unavailable'     => false,
        ]);

        $token = $this->token($demo);

        $response = $this->assertPersistsNothing(
            fn () => $this->withToken($token)->postJson("/api/v1/service-booking/{$link->alias}/quote", [
                'services' => [['service_id' => $service->id, 'quantity' => 2]],
            ]),
            'api/v1/service-booking/*/quote'
        );

        $response->assertOk()->assertJsonPath('data.bill.is_estimate', true);
    }
}
