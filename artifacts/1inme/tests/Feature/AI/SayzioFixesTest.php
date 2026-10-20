<?php

namespace Tests\Feature\AI;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\CompetitorTeardown;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\BlockTypeRegistry;
use App\Modules\User\Support\SayzioFixes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The model may name a Sayzio fix. It may not invent one.
 *
 * A teardown says what a page lacks; the useful other half is what to do
 * about it in Sayzio. Letting the model write that half is the obvious
 * implementation and the dangerous one -- asked to name product features it
 * will produce plausible ones that do not exist, in front of 375K users. So
 * it picks keys from SayzioFixes and nothing else, and everything it
 * returns passes through sanitize() before `analysis` is stored.
 *
 * The route assertion below matters more than it looks. resolve() drops a
 * fix whose route will not resolve, which is correct at runtime and would
 * have quietly hidden a mistake at build time: two of these keys were
 * written as user.subscribers and user.promos when the real names are
 * user.monetization.*. A silent drop is exactly what you do not want from
 * your own typo, so the registry's routes are asserted here instead.
 */
class SayzioFixesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $slug = 'p' . Str::lower(Str::random(8));
        $plan = Plan::create([
            'name' => $slug, 'slug' => $slug, 'monthly_price' => 0,
            'annual_price' => 0, 'trial_days' => 0, 'status' => 'active', 'features' => [],
        ]);

        $user = User::create([
            'name' => 'u' . Str::random(4),
            'email' => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status' => 'active',
            'plan_id' => $plan->id,
        ]);

        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($user));
        app()->instance('workspace_owner', $user);

        return $user;
    }

    private function teardown_(?int $builtLinkId = null): CompetitorTeardown
    {
        $t = new CompetitorTeardown();
        $t->status = 'completed';
        $t->built_link_id = $builtLinkId;

        return $t;
    }

    public function test_every_route_in_the_registry_actually_resolves(): void
    {
        $broken = [];
        foreach (SayzioFixes::all() as $key => $fix) {
            if (($fix['kind'] ?? null) !== SayzioFixes::KIND_PAGE) {
                continue;
            }
            try {
                route($fix['route'], []);
            } catch (\Throwable $e) {
                $broken[] = $key . ' -> ' . $fix['route'];
            }
        }

        sort($broken);
        $this->assertSame([], $broken, 'these fixes name a route that does not exist, so resolve() would silently drop them: ' . implode('; ', $broken));
    }

    public function test_every_block_in_the_registry_is_a_real_block_type(): void
    {
        $unknown = [];
        foreach (SayzioFixes::all() as $key => $fix) {
            if (($fix['kind'] ?? null) !== SayzioFixes::KIND_BLOCK) {
                continue;
            }
            $canonical = BlockTypeRegistry::canonical($fix['block']);
            if (BlockTypeRegistry::meta($canonical) === null) {
                $unknown[] = $key . ' -> ' . $fix['block'];
            }
        }

        $this->assertSame([], $unknown, 'these fixes name a block type the editor does not have: ' . implode('; ', $unknown));
    }

    public function test_invented_keys_never_survive(): void
    {
        $this->assertSame(['lead_form'], SayzioFixes::sanitize([
            'ab_testing',             // plausible product feature that does not exist
            'sayzio_growth_engine',   // invented wholesale
            'Review_Page',            // right idea, wrong case -- not accepted
            ' lead_form ',            // the one real key, untrimmed
            123,
            null,
            ['nested'],
        ]));
    }

    public function test_duplicates_collapse_and_the_cap_holds(): void
    {
        $this->assertSame(['review_page'], SayzioFixes::sanitize(['review_page', 'review_page', 'review_page']));

        $many = array_slice(SayzioFixes::keys(), 0, 10);
        $this->assertCount(6, SayzioFixes::sanitize($many));
        $this->assertCount(2, SayzioFixes::sanitize($many, 2));
    }

    public function test_a_promo_code_is_only_suggested_to_someone_selling_tiers(): void
    {
        $owner = $this->owner();

        $without = SayzioFixes::resolve(['promos', 'review_page'], $owner, $this->teardown_());
        $this->assertSame(['review_page'], array_column($without, 'key'), 'a promo code is a discount on a tier; with no tiers it is noise');

        SubscriptionTier::create([
            'user_id' => $owner->id, 'name' => 'Supporter', 'slug' => 'supporter-' . Str::random(5),
            'is_free' => false, 'is_active' => true, 'sort_order' => 1,
            'price_monthly_cents' => 50000, 'currency' => 'INR',
        ]);

        $with = SayzioFixes::resolve(['promos', 'review_page'], $owner->fresh(), $this->teardown_());
        $this->assertSame(['promos', 'review_page'], array_column($with, 'key'));
    }

    public function test_a_block_waits_for_a_page_to_live_on(): void
    {
        $owner = $this->owner();

        $before = SayzioFixes::resolve(['testimonial_block'], $owner, $this->teardown_());
        $this->assertCount(1, $before, 'the suggestion still shows -- it just has nowhere to go yet');
        $this->assertNull($before[0]['url']);
        $this->assertNotEmpty($before[0]['pending']);

        $after = SayzioFixes::resolve(['testimonial_block'], $owner, $this->teardown_(4242));
        $this->assertStringContainsString('/4242/blocks', (string) $after[0]['url']);
        $this->assertNull($after[0]['pending']);
    }

    public function test_the_prompt_catalogue_offers_exactly_what_can_be_resolved(): void
    {
        $catalogue = SayzioFixes::promptCatalogue();

        foreach (SayzioFixes::keys() as $key) {
            $this->assertStringContainsString($key . ':', $catalogue, "{$key} is resolvable but never offered to the model");
        }

        // And nothing is offered that sanitize() would throw away.
        preg_match_all('/^- ([a-z_]+):/m', $catalogue, $m);
        $this->assertSame(SayzioFixes::keys(), $m[1]);
    }
}
