<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sanctum (bearer-token) coverage for the mobile Brand Consistency score
 * endpoint (GET /api/v1/brand-kits/consistency) and its "Apply fix" → re-audit
 * loop.
 *
 * The endpoint and its mobile UI had no automated coverage, so a future change
 * to {@see \App\Services\Brand\BrandConsistencyService}'s audit shape, the
 * `brand_consistency` plan gate, or the default-kit resolution could silently
 * break the score display or the apply-fix flow. These tests pin:
 *
 *   1. The envelope shape for a gated user with a default kit
 *      (available / has_kit / audit.{score,grade,label,kit_id,findings[...]}).
 *   2. The plan-locked fallback (available=false, has_kit=false, audit=null).
 *   3. The no-kit fallback   (available=true,  has_kit=false, audit=null).
 *   4. The full mobile loop: the off-brand finding's kit_id + link_id drive the
 *      existing apply-to-biolink endpoint, and a re-audit reflects 100.
 *
 * Authenticated requests use a real personal access token, NOT
 * Sanctum::actingAs — that injects a Mockery mock the TouchSessionToken
 * middleware can't ->save() (see the sanctum-api-tests convention).
 *
 * The audit is a pure transformer and apply never calls OpenAI, so these tests
 * need no AI engine and no chat double.
 */
class BrandConsistencyApiTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $featureOverrides = []): Plan
    {
        return Plan::create([
            'name'          => 'Brand Plan',
            'slug'          => 'brand-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 0,
            'features'      => array_merge([
                'max_links'      => 100,
                'max_biolinks'   => 100,
                'max_brand_kits' => 5,
            ], $featureOverrides),
        ]);
    }

    private function makeUser(?Plan $plan = null): User
    {
        return User::factory()->create([
            'role' => 'user',
            'plan_id' => ($plan ?? $this->plan())->id,
        ])->fresh();
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

    private const CONSISTENCY_URL = '/api/v1/brand-kits/consistency';

    private function applyUrl(BrandKit $kit, Link $link): string
    {
        return "/api/v1/brand-kits/{$kit->id}/apply/biolink/{$link->id}";
    }

    // ── auth gate ──────────────────────────────────────────────────────

    public function test_consistency_requires_authentication(): void
    {
        $this->getJson(self::CONSISTENCY_URL)->assertStatus(401);
    }

    // ── envelope shape: gated user with a default kit ──────────────────

    public function test_consistency_returns_the_expected_envelope_for_a_gated_user_with_a_kit(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $link = $this->biolink($user); // off-brand ⇒ produces a finding

        $response = $this->withToken($this->token($user))
            ->getJson(self::CONSISTENCY_URL)
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.has_kit', true)
            ->assertJsonPath('data.audit.kit_id', $kit->id)
            ->assertJsonPath('data.audit.kit_name', 'Aurora Studio')
            ->assertJsonPath('data.audit.links_total', 1)
            ->assertJsonPath('data.audit.links_on_brand', 0);

        // The audit envelope keys the mobile UI reads must all be present.
        $response->assertJsonStructure([
            'data' => [
                'available',
                'has_kit',
                'audit' => [
                    'score',
                    'grade',
                    'label',
                    'kit_id',
                    'kit_name',
                    'links_total',
                    'links_on_brand',
                    'findings' => [
                        '*' => [
                            'link_id',
                            'title',
                            'alias',
                            'score',
                            'severity',
                            'headline',
                            'reason',
                            'mismatches',
                        ],
                    ],
                ],
            ],
        ]);

        $audit = $response->json('data.audit');
        $this->assertLessThan(100, $audit['score']);
        $this->assertNotEmpty($audit['findings']);

        $finding = $audit['findings'][0];
        $this->assertSame($link->id, $finding['link_id']);
        $this->assertStringContainsString('on-brand', $finding['headline']);
        $this->assertNotEmpty($finding['reason']);
        $this->assertNotEmpty($finding['mismatches']);
        // apply_url (a web route) must NOT leak into the mobile envelope —
        // mobile applies via kit_id + link_id.
        $this->assertArrayNotHasKey('apply_url', $finding);
    }

    public function test_consistency_scores_100_with_no_findings_when_kit_is_applied(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $link = $this->biolink($user);

        // Take the page on-brand first.
        app(\App\Services\Brand\AiBrandKitService::class)
            ->applyToBiolink($kit, $link->fresh());

        $this->withToken($this->token($user))
            ->getJson(self::CONSISTENCY_URL)
            ->assertOk()
            ->assertJsonPath('data.audit.score', 100)
            ->assertJsonPath('data.audit.links_on_brand', 1)
            ->assertJsonPath('data.audit.findings', []);
    }

    // ── fallbacks ───────────────────────────────────────────────────────

    public function test_consistency_is_plan_locked_when_the_feature_is_disabled(): void
    {
        // A plan that explicitly defines (and disables) the feature key wins
        // over the legacy default-on fallback.
        $plan = $this->plan(['brand_consistency' => false]);
        $user = $this->makeUser($plan);
        $this->kitFor($user); // even with a kit, a locked plan returns no audit

        $this->withToken($this->token($user))
            ->getJson(self::CONSISTENCY_URL)
            ->assertOk()
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.has_kit', false)
            ->assertJsonPath('data.audit', null);
    }

    public function test_consistency_reports_no_kit_when_the_user_has_none(): void
    {
        $user = $this->makeUser(); // gated, but no brand kit saved

        $this->withToken($this->token($user))
            ->getJson(self::CONSISTENCY_URL)
            ->assertOk()
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.has_kit', false)
            ->assertJsonPath('data.audit', null);
    }

    // ── the full mobile loop: Apply fix → re-audit reflects the fix ────

    public function test_apply_fix_from_a_finding_brings_the_score_to_100(): void
    {
        $user = $this->makeUser();
        $kit  = $this->kitFor($user);
        $link = $this->biolink($user);
        $token = $this->token($user);

        // 1) Initial audit surfaces the off-brand page with the kit_id + link_id
        //    the mobile "Apply fix" button uses.
        $before = $this->withToken($token)
            ->getJson(self::CONSISTENCY_URL)
            ->assertOk()
            ->json('data.audit');
        $this->assertLessThan(100, $before['score']);
        $finding = $before['findings'][0];
        $this->assertSame($link->id, $finding['link_id']);

        // 2) Apply fix: the mobile client posts to the existing apply endpoint
        //    with the audit's kit_id + the finding's link_id (no new path).
        $this->withToken($token)
            ->postJson($this->applyUrl($kit, $link))
            ->assertOk()
            ->assertJsonPath('data.ok', true);

        // 3) Re-audit must now reflect the corrected score.
        $this->withToken($token)
            ->getJson(self::CONSISTENCY_URL)
            ->assertOk()
            ->assertJsonPath('data.audit.score', 100)
            ->assertJsonPath('data.audit.links_on_brand', 1)
            ->assertJsonPath('data.audit.findings', []);
    }
}
