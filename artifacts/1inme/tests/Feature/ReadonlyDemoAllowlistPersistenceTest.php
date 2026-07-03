<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantMenuItem;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
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

    private function makeDemoUser(): User
    {
        $user = User::create([
            'name'              => 'Demo ' . Str::random(4),
            'email'             => 'demo' . Str::random(8) . '@ex.com',
            'password'          => Hash::make('secret-pass'),
            'status'            => 'active',
            'email_verified_at' => now(),
            'onboarded_at'      => now(),
            'is_readonly_demo'  => true,
        ]);
        // Owns a personal workspace so `workspace.can:*` permission gates on the
        // interactive routes pass (owner status grants every permission).
        $user->ensureDefaultWorkspace();

        return $user->fresh();
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
