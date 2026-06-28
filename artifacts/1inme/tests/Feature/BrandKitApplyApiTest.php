<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Brand\BrandConsistencyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sanctum (bearer-token) coverage for the mobile "apply Brand Kit" round-trip
 * (POST /api/v1/brand-kits/{brandKit}/apply/biolink/{link}), the API parity of
 * the web Brand Consistency one-click apply-fix that the browser e2e spec
 * already guards.
 *
 * The whole point of the apply path is that it takes an off-brand page ON
 * brand. {@see BrandConsistencyService::audit()} is the deterministic mirror
 * of {@see \App\Services\Brand\AiBrandKitService::applyToBiolink()}, so the
 * truest regression check is: apply over the API, then re-audit and assert the
 * page scores 100. A silent break in the API apply (wrong kit resolved, wrong
 * settings written, a no-op) would leave the score below 100 and fail here —
 * the kind of regression that would otherwise only surface to mobile users.
 *
 * Authenticated requests use a real personal access token, NOT
 * Sanctum::actingAs — that injects a Mockery mock the TouchSessionToken
 * middleware can't ->save(), so every authed request would 500
 * (see the sanctum-api-tests convention).
 *
 * Apply itself never calls OpenAI (it just writes the saved kit's palette /
 * fonts / block theme), so these tests need no AI engine and no chat double.
 */
class BrandKitApplyApiTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
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
                'max_brand_kits' => 5,
            ],
        ]);
    }

    private function makeUser(): User
    {
        $user = User::create([
            'name'     => 'Brand ' . Str::random(4),
            'email'    => 'brand-' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'role'     => 'user',
            'plan_id'  => $this->plan()->id,
        ]);
        $user->ensureDefaultWorkspace();
        return $user->fresh();
    }

    private function kitFor(User $user): BrandKit
    {
        return BrandKit::create([
            'user_id'    => $user->id,
            'name'       => 'Aurora Studio',
            'slug'       => 'aurora-' . Str::random(6),
            'is_default' => true,
            'config'     => [
                'palette' => [
                    'primary'   => '#3B5BDB',
                    'secondary' => '#5C7CFA',
                    'accent'    => '#F783AC',
                    'neutrals'  => ['#F8F9FA', '#212529'],
                ],
                'fonts'       => ['heading' => 'Poppins', 'body' => 'Inter'],
                'voice'       => ['tone' => 'Warm and confident', 'descriptors' => ['friendly', 'premium']],
                'taglines'    => ['Shine brighter', 'Your brand, elevated'],
                'bio'         => 'A modern studio helping creators look the part.',
                'block_theme' => 'minimal',
            ],
        ]);
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

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function applyUrl(BrandKit $kit, Link $link): string
    {
        return "/api/v1/brand-kits/{$kit->id}/apply/biolink/{$link->id}";
    }

    // ── auth gate ──────────────────────────────────────────────────────

    public function test_apply_requires_authentication(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $link = $this->biolink($user);

        $this->postJson($this->applyUrl($kit, $link))->assertStatus(401);
    }

    // ── the core round-trip: apply takes the page on-brand ─────────────

    public function test_api_apply_takes_the_biolink_on_brand(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $link = $this->biolink($user);

        // The page starts off-brand: nothing on it matches the kit yet.
        $before = app(BrandConsistencyService::class)
            ->audit($kit, collect([$link->fresh()]));
        $this->assertLessThan(100, $before['score']);
        $this->assertNotEmpty($before['findings']);

        $this->withToken($this->token($user))
            ->postJson($this->applyUrl($kit, $link))
            ->assertOk()
            ->assertJsonPath('data.ok', true)
            ->assertJsonPath('data.link.id', $link->id);

        // After applying over the API, the audit (the deterministic mirror of
        // the apply logic) must score the page a perfect 100. If the API path
        // silently broke, this would stay below 100.
        $after = app(BrandConsistencyService::class)
            ->audit($kit, collect([$link->fresh()]));
        $this->assertSame(100, $after['score']);
        $this->assertSame(1, $after['links_on_brand']);
        $this->assertEmpty($after['findings']);
    }

    public function test_api_apply_persists_kit_appearance_onto_the_link(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $link = $this->biolink($user);

        $this->withToken($this->token($user))
            ->postJson($this->applyUrl($kit, $link))
            ->assertOk();

        // Spot-check that the saved kit's appearance actually landed on the row
        // (not just that the response was 200).
        $bio = $link->fresh()->settings['biolink'] ?? [];
        $this->assertSame('#3b5bdb', $bio['button_color'] ?? null); // palette.primary
        $this->assertSame('Inter', $bio['font_family'] ?? null);    // fonts.body
        $this->assertSame('#212529', $bio['font_color'] ?? null);   // darkest neutral
        $this->assertSame('minimal', $bio['block_theme']['_template'] ?? null);
    }

    // ── workspace-scoping parity: can't reach links you shouldn't ──────

    public function test_api_apply_404s_on_a_biolink_owned_by_another_user(): void
    {
        $caller  = $this->makeUser();
        $kit     = $this->kitFor($caller);

        // A biolink that belongs to a different user / workspace entirely.
        $stranger     = $this->makeUser();
        $strangerLink = $this->biolink($stranger);

        $this->withToken($this->token($caller))
            ->postJson($this->applyUrl($kit, $strangerLink))
            ->assertStatus(404);

        // The stranger's page must be untouched (never went on-brand).
        $audit = app(BrandConsistencyService::class)
            ->audit($kit, collect([$strangerLink->fresh()]));
        $this->assertLessThan(100, $audit['score']);
    }

    public function test_api_apply_404s_when_the_kit_belongs_to_another_user(): void
    {
        $caller = $this->makeUser();
        $link   = $this->biolink($caller);

        // A kit owned by someone else — the caller must not be able to wield it.
        $stranger    = $this->makeUser();
        $strangerKit = $this->kitFor($stranger);

        $this->withToken($this->token($caller))
            ->postJson($this->applyUrl($strangerKit, $link))
            ->assertStatus(404);

        // The caller's own page must be untouched.
        $bio = $link->fresh()->settings['biolink'] ?? [];
        $this->assertArrayNotHasKey('button_color', $bio);
    }

    public function test_api_apply_404s_on_a_non_biolink_link(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);

        // A short link the caller owns, but apply-to-biolink is biolink-only.
        $short = Link::create([
            'user_id'      => $user->id,
            'workspace_id' => app(WorkspaceContext::class)->resolve($user)?->id,
            'type'         => 'short',
            'alias'        => Str::random(8),
            'long_url'     => 'https://example.com',
            'is_active'    => true,
        ]);

        $this->withToken($this->token($user))
            ->postJson($this->applyUrl($kit, $short))
            ->assertStatus(404);
    }
}
