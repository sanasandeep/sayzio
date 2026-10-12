<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\Common\Support\SitePagesContent;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Web counterpart to the mobile "Perfect pairings" post-auth redirect guard
 * (artifacts/1inme-mobile/scripts/test-auth-next.mjs).
 *
 * The web app renders the same cross-promo surface via
 * common/partials/link-type-pairings.blade.php, driven by
 * SitePagesContent::linkTypePairingsCatalog() +
 * ::linkTypePairingCreateRoute(). A guest who taps a pairing card is sent to
 * signup with a `?redirect=` back to the type-specific create screen; a
 * logged-in creator is linked straight there. showRegister() stashes that
 * redirect under Laravel's `url.intended` session key so both the password and
 * OTP sign-up paths honour it via redirect()->intended(dashboard) — falling
 * back to the dashboard when nothing is pending.
 *
 * This pins that whole handoff so a silent break can't quietly kill the same
 * guest -> creator conversion on the web that the mobile test protects:
 *   1. every catalog `type` maps to the correct, resolvable create route
 *      (and an unmapped/new type degrades to the generic create flow);
 *   2. a guest card links through signup with the create URL as `?redirect=`,
 *      while a logged-in creator's card links straight to the create screen;
 *   3. showRegister() stashes only a same-app redirect (open redirects are
 *      dropped);
 *   4. finishing signup lands on the stashed create screen, else the dashboard.
 */
class LinkTypePairingsWebRedirectTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Mirror of the mobile expectedRoutes table, but for the WEB create-flow
     * route names each catalog `type` must deep-link to. Kept explicit (not
     * derived from the helper) so a wrong or dropped mapping is caught, and so
     * a NEW catalog type that lacks a deliberate mapping fails this test.
     */
    private const EXPECTED_ROUTES = [
        'calendar'        => 'user.calendars.create',
        'biolink'         => 'user.links.biolink.create',
        'reviews'         => 'user.links.reviews.create',
        'vcf'             => 'user.links.vcf.create',
        'resume'          => 'user.links.resume.create',
        'brand_kit'       => 'user.links.brand-kit.create',
        'qr'              => 'user.qr-codes.create',
        'ics'             => 'user.links.ics.create',
        // These two have no dedicated create route; they pre-select the type
        // on the generic create flow via a `type` query param.
        'restaurant_menu' => 'user.links.create',
        'store_menu'      => 'user.links.create',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        // The register view uses @vite; swap it for a no-op so the page renders
        // without a built manifest in the test environment.
        $this->withoutVite();

        // OTP signup assigns the default (free) plan to the new account.
        Plan::firstOrCreate(['slug' => 'free'], [
            'name' => 'Free', 'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'grace_days' => 0, 'refund_window_days' => 0,
            'status' => 'active', 'sort_order' => 0, 'features' => [],
            'is_default' => true,
        ]);
    }

    /**
     * Every `type` in the pairings catalog must map to the expected, resolvable
     * web create route — the exact lockstep the partial relies on when building
     * each card's destination.
     */
    public function test_every_catalog_type_maps_to_the_correct_create_route(): void
    {
        $seen = [];

        foreach (SitePagesContent::linkTypePairingsCatalog() as $pairingKey => $items) {
            foreach ($items as $item) {
                $type = $item['type'] ?? null;
                $this->assertNotNull($type, "pairing under '{$pairingKey}' is missing a type");
                $seen[$type] = true;

                $this->assertArrayHasKey(
                    $type,
                    self::EXPECTED_ROUTES,
                    "catalog type '{$type}' has no deliberate create-route mapping in this guard",
                );

                [$routeName, $params] = SitePagesContent::linkTypePairingCreateRoute($type);

                $this->assertSame(
                    self::EXPECTED_ROUTES[$type],
                    $routeName,
                    "pairing type '{$type}' should deep-link to route {$this->constExpected($type)}",
                );

                // The generic-create fallbacks must pre-select their type.
                if (in_array($type, ['restaurant_menu', 'store_menu'], true)) {
                    $this->assertSame(['type' => $type], $params);
                }

                // The route must actually resolve to a usable, same-app URL —
                // route() throws for an unknown name, so this fails loudly if a
                // create route is renamed or removed out from under a pairing.
                $url = route($routeName, $params);
                $this->assertNotEmpty($url);
                $this->assertStringStartsWith(url('/'), $url, "{$type} create URL must be same-app");
            }
        }

        // Sanity: the catalog actually exercised a spread of types.
        $this->assertGreaterThanOrEqual(6, count($seen));
    }

    /**
     * The whole point of the cross-promo: a guest who taps a pairing card is
     * carried through signup onto the type-specific create route (guarded by
     * LinkTypePairingsWebRedirectTest::test_signup_completion_lands_on_the_stashed_create_screen
     * and Task #3689). This pins the *next* step — that the brand-new, free-plan
     * account can actually LOAD every one of those create screens with a usable
     * 200, instead of being silently bounced to /user/upgrade (or the login /
     * dashboard). If any pairing lands a just-converted free user on a paywall,
     * the pairing promise becomes a conversion dead-end, so this fails loudly.
     */
    public function test_fresh_free_user_can_open_every_pairing_create_screen(): void
    {
        $free = Plan::where('slug', 'free')->firstOrFail();

        // A brand-new account exactly as OTP signup leaves it: default (free)
        // plan, verified, onboarded (so the onboarding gate doesn't redirect),
        // owning its personal workspace so `workspace.can:links.create` passes.
        $user = User::create([
            'name'              => 'Fresh Free User',
            'email'             => 'fresh' . Str::lower(Str::random(6)) . '@example.com',
            'password'          => Hash::make('secret-pass'),
            'status'            => 'active',
            'email_verified_at' => now(),
            'onboarded_at'      => now(),
            'plan_id'           => $free->id,
        ]);
        $user->ensureDefaultWorkspace();
        $user = $user->fresh();

        $this->actingAs($user, 'web');

        // Exercise the exact routes each catalog `type` deep-links to, deduped
        // so a type shared across several pairing pages is only hit once.
        $seenTypes = [];
        foreach (SitePagesContent::linkTypePairingsCatalog() as $items) {
            foreach ($items as $item) {
                $seenTypes[$item['type']] = true;
            }
        }

        foreach (array_keys($seenTypes) as $type) {
            [$name, $params] = SitePagesContent::linkTypePairingCreateRoute($type);
            $createUrl = route($name, $params);

            $response = $this->get($createUrl);

            // A paywall / permission bounce is a redirect (302 → /user/upgrade
            // or /user/dashboard) or a 403 denial page, never a rendered create
            // screen. So a plain 200 IS the guarantee the pairing promise holds:
            // the brand-new free account actually reaches the create form. The
            // (ever-present header/sidebar) "Upgrade" link is not a bounce, so we
            // deliberately assert on the response *status*, not page copy.
            $status = $response->getStatusCode();
            $this->assertSame(
                200,
                $status,
                "the '{$type}' create screen (" . $createUrl . ") must load for a fresh free "
                . 'user, not bounce to '
                . ($response->headers->get('Location') ?: 'a ' . $status . ' response'),
            );
        }

        // Sanity: we actually covered a real spread of pairing create screens
        // (the distinct catalog *item* types — note `resume`/`store_menu` are
        // pairing page keys, not item types, so the count is < EXPECTED_ROUTES).
        $this->assertGreaterThanOrEqual(6, count($seenTypes));
    }

    /**
     * An unknown / newly-added type with no dedicated create route degrades to
     * the generic create flow rather than throwing — mirrors the mobile
     * "unknown pairing type falls back to the generic Create tab" assertion.
     */
    public function test_unknown_type_falls_back_to_the_generic_create_flow(): void
    {
        $this->assertSame(
            ['user.links.create', []],
            SitePagesContent::linkTypePairingCreateRoute('something_brand_new'),
        );
    }

    /**
     * Guest render: each pairing card links to signup carrying the create URL
     * as a same-app `?redirect=` (stash + redirect path).
     */
    public function test_guest_cards_route_through_signup_with_a_redirect_back_to_create(): void
    {
        foreach (array_keys(SitePagesContent::linkTypePairingsCatalog()) as $pairingKey) {
            $html = view('common.partials.link-type-pairings', [
                'pairingType' => $pairingKey,
                'theme'       => 'dark',
            ])->render();

            foreach (SitePagesContent::linkTypePairingsFor($pairingKey) as $item) {
                [$name, $params] = SitePagesContent::linkTypePairingCreateRoute($item['type']);
                $createUrl = route($name, $params);
                $expectedHref = route('user.register') . '?redirect=' . urlencode($createUrl);

                $this->assertStringContainsString(
                    'href="' . e($expectedHref) . '"',
                    $html,
                    "guest '{$pairingKey}' card for '{$item['type']}' must route through signup with a redirect",
                );
            }
        }
    }

    /**
     * Logged-in render: each pairing card links straight to the create screen,
     * with no signup detour (direct path).
     */
    public function test_logged_in_cards_link_directly_to_the_create_screen(): void
    {
        $user = User::create([
            'name'     => 'Pairing Creator',
            'email'    => 'pairing-creator@example.com',
            'password' => Hash::make('secret-pass'),
            'status'   => 'active',
        ]);

        $this->actingAs($user, 'web');

        foreach (array_keys(SitePagesContent::linkTypePairingsCatalog()) as $pairingKey) {
            $html = view('common.partials.link-type-pairings', [
                'pairingType' => $pairingKey,
                'theme'       => 'dark',
            ])->render();

            foreach (SitePagesContent::linkTypePairingsFor($pairingKey) as $item) {
                [$name, $params] = SitePagesContent::linkTypePairingCreateRoute($item['type']);
                $createUrl = route($name, $params);

                $this->assertStringContainsString(
                    'href="' . e($createUrl) . '"',
                    $html,
                    "logged-in '{$pairingKey}' card for '{$item['type']}' must link straight to create",
                );
                // ...and must NOT bounce a signed-in creator back through signup.
                $this->assertStringNotContainsString(
                    'redirect=' . urlencode($createUrl),
                    $html,
                    "logged-in '{$pairingKey}' card for '{$item['type']}' must not detour through signup",
                );
            }
        }
    }

    /**
     * showRegister() stashes a same-app pairing redirect under `url.intended`
     * so the completing sign-up honours it for free.
     */
    public function test_register_page_stashes_a_same_app_pairing_redirect(): void
    {
        $createUrl = route(...SitePagesContent::linkTypePairingCreateRoute('reviews'));

        $this->get(route('user.register') . '?redirect=' . urlencode($createUrl))
            ->assertOk();

        $this->assertSame($createUrl, session('url.intended'));
    }

    /**
     * A crafted off-site `redirect` must never be stashed — sign-up can't be
     * turned into an open redirect (mirrors the mobile sanitizeNext rejection
     * of external / protocol-relative paths).
     */
    public function test_register_page_drops_an_off_site_redirect(): void
    {
        foreach (['https://evil.example.com/steal', '//evil.example.com'] as $evil) {
            session()->forget('url.intended');

            $this->get(route('user.register') . '?redirect=' . urlencode($evil))
                ->assertOk();

            $this->assertNull(
                session('url.intended'),
                "an off-site redirect ({$evil}) must not be stashed",
            );
        }
    }

    /**
     * Finishing signup lands the brand-new creator on the stashed create
     * screen — the guest who tapped a pairing card arrives exactly where the
     * card promised.
     */
    public function test_signup_completion_lands_on_the_stashed_create_screen(): void
    {
        $createUrl = route(...SitePagesContent::linkTypePairingCreateRoute('store_menu'));
        $email = 'pair' . Str::lower(Str::random(6)) . '@example.com';

        // The card sent the guest to signup with the create URL stashed.
        $this->post('/user/send-otp', [
            'identifier' => $email,
            'type'       => 'email',
            'intent'     => 'signup',
        ])->assertRedirect(route('user.otp.verify.form'));

        $user = User::where('email', $email)->first();
        $this->assertNotNull($user, 'signup intent must create the account');

        // Complete the OTP step with the pairing redirect still pending.
        $code = (new OtpService())->generate($email, 'email', 'login', 'web');
        $this->withSession([
                'otp_identifier' => $email,
                'otp_type'       => 'email',
                'url.intended'   => $createUrl,
            ])
            ->post('/user/verify-otp', ['code' => $code])
            ->assertRedirect($createUrl);

        $this->assertAuthenticatedAs($user->fresh());
    }

    /**
     * With nothing pending (or an expired redirect), signup completion falls
     * back to the dashboard rather than dead-ending — mirrors the mobile
     * "with nothing pending, fall back to the tabs home" assertion.
     */
    public function test_signup_completion_without_a_pending_redirect_falls_back_to_dashboard(): void
    {
        $email = 'nopair' . Str::lower(Str::random(6)) . '@example.com';

        $this->post('/user/send-otp', [
            'identifier' => $email,
            'type'       => 'email',
            'intent'     => 'signup',
        ])->assertRedirect(route('user.otp.verify.form'));

        $code = (new OtpService())->generate($email, 'email', 'login', 'web');
        $this->withSession(['otp_identifier' => $email, 'otp_type' => 'email'])
            ->post('/user/verify-otp', ['code' => $code])
            ->assertRedirect(route('user.dashboard'));
    }

    /** Readable route name for a failed mapping assertion. */
    private function constExpected(string $type): string
    {
        return self::EXPECTED_ROUTES[$type] ?? '(none)';
    }
}
