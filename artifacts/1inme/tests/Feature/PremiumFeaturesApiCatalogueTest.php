<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\User;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile-side counterpart to {@see PremiumFeaturesPageRenderTest} (the web
 * /premium-features render test). The mobile Premium Features screen is fed by
 * the SAME catalogue ({@see \App\Modules\Common\Support\PremiumFeatures::catalogue()}
 * + {@see \App\Modules\Common\Support\PremiumFeatures::resolveCell()}) via the
 * Sanctum billing endpoint GET /api/v1/billing/plans
 * ({@see \App\Modules\Api\Controllers\RevenueCatBillingController::plans}):
 *   - `data.premium_features` carries every catalogue entry (group, name,
 *     description, unit + `unlocked_by` plan slugs); the screen groups + lists
 *     these.
 *   - `data.plans[].feature_highlights` carries the per-plan plain-English
 *     bullets where `resolveCell()` surfaces the numeric / "Unlimited" cell
 *     values ("Short links — 500 links", "Link in Bio pages — Unlimited pages").
 *
 * Nothing exercised this endpoint against real Plan rows, so a catalogue or
 * plan-shape change could blank or diverge the mobile screen while the web grid
 * stays fine. This boots the endpoint end-to-end and mirrors the web assertions
 * (a known group label, a known feature name, an Unlimited cell and a numeric
 * cell) so the two surfaces can't silently drift.
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which injects a mock that breaks the TouchSessionToken
 * middleware — every authed request would 500). See sanctum-api-tests.md.
 */
class PremiumFeaturesApiCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Test ' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    /**
     * Create an active, public, priced plan at the given sort position with the
     * supplied `features` JSON — mirrors the helper in the web render test.
     */
    private function makePlan(string $name, int $sortOrder, array $features): Plan
    {
        $plan = Plan::create([
            'name'          => $name,
            'slug'          => Str::slug($name) . '-' . Str::random(6),
            'description'   => $name . ' plan',
            'monthly_price' => 10.00 + $sortOrder,
            'annual_price'  => 100.00 + $sortOrder,
            'trial_days'    => 0,
            'status'        => 'active',
            'is_archived'   => false,
            'is_internal'   => false,
            'sort_order'    => $sortOrder,
            'features'      => $features,
        ]);

        PricingResolver::upsertManyFromMinor($plan, [
            ['USD', 'monthly', (10 + $sortOrder) * 100],
            ['USD', 'annual', (100 + $sortOrder) * 100],
            ['INR', 'monthly', (10 + $sortOrder) * 100 * 80],
            ['INR', 'annual', (100 + $sortOrder) * 100 * 80],
        ]);

        return $plan;
    }

    /**
     * Start from a clean slate so the only plans in the response are the ones we
     * create here. Prices carry an FK to plans, so they go first.
     */
    private function clearSeededPlans(): void
    {
        foreach (Plan::all() as $plan) {
            $plan->prices()->delete();
        }
        Plan::query()->delete();
    }

    public function test_billing_plans_endpoint_serves_premium_catalogue_against_real_plans(): void
    {
        $this->clearSeededPlans();

        // A plan with: a numeric allowance (max_links = 500) and an Unlimited
        // numeric (max_biolinks = -1) — the two cell kinds the web grid renders.
        $starter = $this->makePlan('Starter', 1, [
            'max_links'      => 500,
            'max_biolinks'   => -1,
            'custom_domains' => true,
        ]);

        // A second plan so the catalogue genuinely resolves more than one column.
        $this->makePlan('Growth', 2, [
            'max_links'      => 5000,
            'max_biolinks'   => 10,
            'custom_domains' => false,
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($this->makeUser())])
            ->getJson('/api/v1/billing/plans')
            ->assertOk();

        // ---- The shared catalogue feeds the mobile premium_features list ----
        $features = collect($res->json('data.premium_features'));
        $this->assertNotEmpty($features, 'premium_features list missing from /billing/plans response');

        // A known group label from the catalogue is present.
        $this->assertTrue(
            $features->contains('group', 'Limits'),
            'known catalogue group "Limits" missing from premium_features'
        );

        // Known feature-row labels are present, carrying their group + unit.
        $shortLinks = $features->firstWhere('key', 'max_links');
        $this->assertNotNull($shortLinks, 'max_links entry missing from premium_features');
        $this->assertSame('Short links', $shortLinks['name']);
        $this->assertSame('Limits', $shortLinks['group']);
        $this->assertSame('links', $shortLinks['unit']);

        $biolinks = $features->firstWhere('key', 'max_biolinks');
        $this->assertNotNull($biolinks, 'max_biolinks entry missing from premium_features');
        $this->assertSame('Link in Bio pages', $biolinks['name']);

        // unlocked_by is computed from resolveCell() against the real plans: both
        // priced plans unlock short links; only Starter has Unlimited biolinks.
        $this->assertContains($starter->slug, $shortLinks['unlocked_by']);

        // ---- resolveCell() surfaces the numeric / Unlimited cells on plans ----
        $starterPlan = collect($res->json('data.plans'))->firstWhere('id', $starter->id);
        $this->assertNotNull($starterPlan, 'Starter plan missing from /billing/plans response');
        $highlights = $starterPlan['feature_highlights'];

        // Numeric cell: max_links = 500 renders formatted in the highlight bullet.
        $this->assertContains('Short links — 500 links', $highlights);

        // Unlimited cell: max_biolinks = -1 renders as "Unlimited".
        $this->assertContains('Link in Bio pages — Unlimited pages', $highlights);
    }

    /**
     * The numeric / "Unlimited" cells are covered above; this guards the other
     * two highlight kinds {@see \App\Modules\Common\Support\PremiumFeatures::planHighlights()}
     * emits and — critically — their ORDER, none of which was exercised before:
     *   - a boolean capability renders as the bare feature name ("Custom domains"),
     *   - the `analytics` select renders "Analytics depth — Advanced" (and only
     *     when advanced — a Basic plan never lists it, because resolveCell()
     *     reports analytics as "off" unless it is advanced), and
     *   - highlights stay in catalogue() order, so a catalogue reorder or a
     *     resolveCell() phrasing tweak can't silently reword/reshuffle the mobile
     *     plan cards while the web grid stays fine.
     */
    public function test_billing_plans_feature_highlights_cover_boolean_analytics_and_order(): void
    {
        $this->clearSeededPlans();

        // A plan exercising all three highlight kinds at known catalogue
        // positions: max_links (Limits) precedes analytics (Tracking &
        // analytics) precedes custom_domains (Domains & SEO).
        $pro = $this->makePlan('Pro', 1, [
            'max_links'      => 500,
            'analytics'      => 'advanced',
            'custom_domains' => true,
        ]);

        // A Basic-analytics plan: resolveCell() reports analytics as "off" unless
        // it is advanced, so this plan must NOT surface any analytics bullet.
        $basic = $this->makePlan('Basic', 2, [
            'max_links' => 50,
            'analytics' => 'basic',
        ]);

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->token($this->makeUser())])
            ->getJson('/api/v1/billing/plans')
            ->assertOk();

        $proPlan = collect($res->json('data.plans'))->firstWhere('id', $pro->id);
        $this->assertNotNull($proPlan, 'Pro plan missing from /billing/plans response');
        $highlights = $proPlan['feature_highlights'];

        // Boolean capability renders as the bare feature name (no " — value").
        $this->assertContains('Custom domains', $highlights);

        // Analytics select renders the chosen depth when unlocked.
        $this->assertContains('Analytics depth — Advanced', $highlights);

        // Numeric allowance renders the formatted value + unit (mirrors the
        // existing numeric-cell coverage, used here as the order anchor).
        $this->assertContains('Short links — 500 links', $highlights);

        // ---- Highlights appear in catalogue() order ----
        $posShortLinks = array_search('Short links — 500 links', $highlights, true);
        $posAnalytics  = array_search('Analytics depth — Advanced', $highlights, true);
        $posDomains    = array_search('Custom domains', $highlights, true);

        $this->assertIsInt($posShortLinks);
        $this->assertIsInt($posAnalytics);
        $this->assertIsInt($posDomains);
        $this->assertLessThan($posAnalytics, $posShortLinks, 'Short links must precede Analytics depth (catalogue order)');
        $this->assertLessThan($posDomains, $posAnalytics, 'Analytics depth must precede Custom domains (catalogue order)');

        // ---- A Basic-analytics plan never lists the analytics bullet ----
        $basicPlan = collect($res->json('data.plans'))->firstWhere('id', $basic->id);
        $this->assertNotNull($basicPlan, 'Basic plan missing from /billing/plans response');
        $this->assertNotContains(
            'Analytics depth — Basic',
            $basicPlan['feature_highlights'],
            'Basic analytics must not surface an analytics highlight (resolveCell reports it off)'
        );
        $this->assertNotContains(
            'Analytics depth — Advanced',
            $basicPlan['feature_highlights'],
            'Basic-analytics plan must not claim Advanced analytics'
        );
    }

    public function test_billing_plans_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/billing/plans')->assertStatus(401);
    }
}
