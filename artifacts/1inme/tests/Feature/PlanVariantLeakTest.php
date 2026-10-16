<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Support\PlanSetRoller;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use App\Services\PricingResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A plan category is a bucket of near-identical plans competing for ONE slot.
 * /pricing has always reduced them. Nothing else did.
 *
 * That was survivable only because no variants existed yet. The moment
 * `plans:generate-variants` runs, every surface that lists plans without the
 * reduction shows nine Bio plans a few rupees apart with identical features —
 * and the upgrade and downgrade lists do worse than look silly: they offer a
 * paying customer a sibling of the plan they already hold, as a move worth
 * making.
 *
 * So this file guards the reduction in two ways. The first tests are
 * behavioural, on the surfaces that touch money. The last is structural, and
 * exists because the real bug was never one wrong line — it was that one
 * place out of six knew categories existed, and nothing said so.
 */
class PlanVariantLeakTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_upgrade_list_never_offers_a_sibling_of_the_plan_you_hold(): void
    {
        [$user, $sub] = $this->subscribedTo($this->variants('leak-bio', [16000, 17800, 18900])[1]);
        $this->variants('leak-pro', [90000, 100000, 110000]);

        $plans = $this->actingAs($user)
            ->get(route('user.billing.upgrade'))
            ->assertOk()
            ->viewData('plans');

        $this->assertSame(
            [],
            $plans->where('plan_category', 'leak-bio')->pluck('name')->all(),
            'the upgrade list offered a variant of the category the customer is already in — same features, a few rupees more',
        );
        $this->assertOnePerCategory($plans, 'the upgrade list');
        // The genuinely higher category IS still offered — the exclusion
        // above must remove siblings, not the whole upgrade path. (Count is
        // deliberately not pinned: the seeded catalogue sits in this database
        // too, and most of it is dearer than the fixture.)
        $this->assertContains(
            'leak-pro',
            $plans->map(fn (Plan $p) => $p->categoryKey())->all(),
            'excluding siblings also removed the real upgrade',
        );
    }

    public function test_the_downgrade_list_never_offers_a_sibling_either(): void
    {
        $bio = $this->variants('leak-bio', [16000, 17800, 18900]);
        [$user] = $this->subscribedTo($this->variants('leak-pro', [90000, 100000, 110000])[1]);

        $plans = $this->actingAs($user)
            ->get(route('user.billing.downgrade'))
            ->assertOk()
            ->viewData('plans');

        $this->assertSame(
            [],
            $plans->where('plan_category', 'leak-pro')->pluck('name')->all(),
            'the downgrade list offered a cheaper variant of the same category, which is churn for no reason',
        );
        $this->assertOnePerCategory($plans, 'the downgrade list');
        $this->assertContains($bio[0]->categoryKey(), $plans->map->categoryKey()->all());
    }

    /**
     * A variant belongs to its anchor's band. Before the group map was
     * widened to cover category siblings, every generated variant counted as
     * ungrouped, so the curated grid rendered its nine cards and then
     * repeated them under the "More plans" catch-all.
     */
    public function test_generated_variants_do_not_fall_into_the_ungrouped_band(): void
    {
        $this->artisan('plans:generate-variants')->assertSuccessful();
        $this->artisan('plans:audit-public')->assertSuccessful();

        $curated = $this->withCookie(PlanSetRoller::COOKIE, '1')->get('/pricing')->assertOk();

        $this->assertNotContains(
            'ungrouped',
            array_column($curated->viewData('planBands'), 'key'),
            'the curated page repeated its own plans under the ungrouped catch-all',
        );
        $curated->assertDontSee('More plans', false);
    }

    /**
     * The structural guard. Every query that LISTS public plans has to make
     * the reduction; a query that looks ONE up by id must not, or a customer
     * shown a variant could not buy it.
     *
     * The split is visible in the code: a listing ends in ->get(), a lookup
     * ends in find()/findOrFail(). So this reads the app tree and requires
     * any file that lists public plans to name the resolver. It is a crude
     * check and deliberately so — it fails when someone adds a seventh plan
     * listing, which is precisely how the first six came to disagree.
     */
    public function test_every_public_plan_listing_goes_through_the_variant_resolver(): void
    {
        // The one query allowed to see every variant. PricingPageCache holds
        // the whole catalogue because the reduction is per-visitor and has to
        // happen AFTER the cache — a cached representative would quote one
        // visitor's price to everybody.
        $allowed = ['Modules/Common/Support/PricingPageCache.php'];

        $root = app_path();
        $offenders = [];

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            $relative = str_replace($root.'/', '', $file->getPathname());

            if (in_array($relative, $allowed, true) || str_contains($source, 'PlanVariantResolver')) {
                continue;
            }

            // A Plan LISTING: ->public() with a ->get() nearby and the Plan
            // model in the same breath. A lookup by id has no ->get(), so
            // find()/findOrFail() never trip this — which is deliberate: a
            // customer shown a variant has to be able to buy it.
            foreach ($this->matchOffsets($source, '->public()') as $at) {
                $window = substr($source, max(0, $at - 400), 800);
                if (str_contains($window, 'Plan') && str_contains($window, '->get()')) {
                    $offenders[] = $relative;
                    break;
                }
            }
        }

        sort($offenders);
        $this->assertSame([], $offenders, implode("\n", [
            'These files list public plans without reducing each category to one variant.',
            'Once plans:generate-variants runs they will show every near-identical sibling at once:',
            '  '.implode("\n  ", $offenders),
        ]));
    }

    /** @return list<int> */
    private function matchOffsets(string $haystack, string $needle): array
    {
        $out = [];
        $at = 0;
        while (($at = strpos($haystack, $needle, $at)) !== false) {
            $out[] = $at;
            $at += strlen($needle);
        }

        return $out;
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /** @param \Illuminate\Support\Collection<int, Plan> $plans */
    private function assertOnePerCategory($plans, string $what): void
    {
        $categories = $plans->map(fn (Plan $p) => $p->categoryKey())->all();
        $this->assertSame(
            array_values(array_unique($categories)),
            array_values($categories),
            "{$what} showed two plans from one category",
        );
    }

    /**
     * A category holding several near-identical plans. The first is the
     * category default, as the generator leaves it.
     *
     * @param  list<int>  $inrMonthlyMinor
     * @return list<Plan>
     */
    private function variants(string $category, array $inrMonthlyMinor): array
    {
        $out = [];
        foreach (array_values($inrMonthlyMinor) as $i => $minor) {
            $plan = Plan::create([
                'name' => ucfirst($category)." variant {$i}",
                'slug' => Str::slug($category."-variant-{$i}"),
                'plan_category' => $category,
                'is_category_default' => $i === 0,
                'description' => 'Near-identical sibling.',
                'monthly_price' => $minor / 100,
                'annual_price' => ($minor * 10) / 100,
                'trial_days' => 0,
                'grace_days' => 7,
                'status' => 'active',
                'is_default' => false,
                'is_archived' => false,
                'is_internal' => false,
                'sort_order' => $i,
                'features' => [],
            ]);
            PricingResolver::upsertManyFromMinor($plan, [
                ['INR', 'monthly', $minor],
                ['INR', 'annual', $minor * 10],
                ['USD', 'monthly', (int) round($minor / 80)],
                ['USD', 'annual', (int) round(($minor * 10) / 80)],
            ]);
            $out[] = $plan;
        }

        return $out;
    }

    /** @return array{0: User, 1: Subscription} */
    private function subscribedTo(Plan $plan): array
    {
        $user = User::create([
            'name' => 'Holder '.Str::random(4),
            'email' => 'h'.Str::random(8).'@e.com',
            'password' => Hash::make('secret'),
            'status' => 'active',
            'country' => 'IN',
        ]);
        BillingAddress::create([
            'user_id' => $user->id, 'country' => 'IN', 'region' => 'MH',
            'postal_code' => '400001', 'line1' => '1 Rd', 'city' => 'Mumbai',
        ]);

        $sub = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'currency' => 'INR',
            'gateway' => 'offline',
            'cancel_at_period_end' => false,
            'current_period_start' => Carbon::now()->subDays(5),
            'current_period_end' => Carbon::now()->addDays(25),
        ]);

        return [$user, $sub];
    }
}
