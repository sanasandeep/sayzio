<?php

namespace Tests\Feature;

use App\Modules\Common\Support\HomePageCache;
use App\Modules\Admin\Models\Plan;
use App\Services\PricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The homepage quotes the visitor's own currency, and the footer can move it.
 *
 * It did neither. Measured on the live site in one session: the footer's
 * switcher showed INR selected, /pricing listed every plan from Rs 167, and
 * the homepage teaser three sections above said "Paid plans from $4.00 /
 * month" -- and clicking INR in the footer left it there.
 *
 * Two independent faults, both of them here:
 *
 *  1. The payload is CACHED per currency but was BUILT by re-deriving the
 *     currency from the ambient request (`priceFor($plan, $user)` with the
 *     null $user every caller passes, which falls through to session, cookie
 *     and geo). On the request path that lands on the visitor's currency by
 *     luck. The scheduled warmer is where it breaks: it runs on the CLI with
 *     no session and no IP, resolves to the default, and writes USD figures
 *     into the INR-keyed entry.
 *
 *  2. The teaser that is actually live (`compact-conversion`, the section the
 *     deferred fragment renders) printed that single-currency key and had no
 *     listener for the footer's `inme-currency` event -- so the one control
 *     the page offers for the figure could not reach it. The listener had
 *     been added to `home/partials/pricing`, which this design does not
 *     render.
 *
 * Hence both halves below: the payload must honour the currency it was asked
 * for, and every home partial that prints a plan price must follow the
 * switcher.
 */
class TheHomeTeaserQuotesTheVisitorsCurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A catalogue priced in both currencies, with deliberately unrelated
     * amounts: 700 minor in USD, 16,700 in INR. Nothing here is a conversion
     * of anything else, so a figure rendered in the wrong currency cannot
     * coincidentally look right.
     */
    private function seedCatalogue(): void
    {
        Plan::query()->update(['is_archived' => true, 'status' => 'inactive']);

        $this->plan('Starter', 0, 0, false, 0);
        $this->plan('Lite', 700, 16700, false, 1);
        $this->plan('Professional', 1900, 45300, true, 2);
    }

    private function plan(string $name, int $usdMinor, int $inrMinor, bool $popular, int $order): Plan
    {
        $plan = Plan::create([
            'name'        => $name,
            'slug'        => 'cur-' . Str::slug($name),
            'description' => $name . ' plan',
            'status'      => 'active',
            'is_archived' => false,
            'is_popular'  => $popular,
            'sort_order'  => $order,
            'features'    => [],
        ]);

        PricingResolver::upsertFromMinor($plan, 'USD', 'monthly', $usdMinor);
        PricingResolver::upsertFromMinor($plan, 'INR', 'monthly', $inrMinor);

        return $plan;
    }

    /** Every home partial that can print a plan price. */
    private function pricePrintingPartials(): array
    {
        $dir = resource_path('views/home/partials');
        $found = [];

        foreach (glob($dir . '/*.blade.php') ?: [] as $path) {
            $source = file_get_contents($path);

            if ($source === false) {
                continue;
            }

            // A partial prints a plan price if it reaches for the teaser
            // payload at all. Both names are view-local variables, so this
            // cannot match a partial that merely mentions pricing in copy.
            if (preg_match('/\$cheapestPaidPlan|\$publicPaid|\$cheapestPaid\b/', $source)) {
                $found[basename($path)] = $source;
            }
        }

        return $found;
    }

    /**
     * The payload prices in the currency it was built for.
     *
     * Asked for USD it must be USD; asked for INR it must be INR. A builder
     * that ignores its argument -- which is exactly what shipped -- fails one
     * of the two, whichever way it happens to resolve.
     */
    public function test_the_payload_prices_in_the_currency_it_was_built_for(): void
    {
        $this->seedCatalogue();

        $wrong = [];

        foreach (HomePageCache::CURRENCIES as $currency) {
            $payload = HomePageCache::buildPayload(null, null, false, $currency);

            $cheapest = $payload['cheapestPaid'] ?? null;
            $this->assertNotNull($cheapest, "no cheapestPaid in the {$currency} payload");

            $got = $cheapest['monthly']['currency'] ?? '(none)';
            if ($got !== $currency) {
                $wrong[] = "the {$currency} payload's starting price is in {$got}";
            }

            foreach ($payload['plans'] as $plan) {
                $got = $plan['monthly']['currency'] ?? '(none)';
                if ($got !== $currency) {
                    $wrong[] = sprintf(
                        "the %s payload's '%s' card is priced in %s",
                        $currency,
                        $plan['name'] ?? '?',
                        $got
                    );
                }
            }
        }

        $this->assertSame([], $wrong, sprintf(
            "The home payload is cached per currency but not BUILT per currency, so the\n"
            . "warmer writes one currency's figures under every key and the homepage quotes\n"
            . "a price the visitor does not pay in.\n\n%s",
            implode("\n", $wrong)
        ));
    }

    /**
     * The control for the test above: the fixture really does price the two
     * currencies differently.
     *
     * Without this, a resolver that returned the same amount for every
     * currency would make the assertion above unfailable, and a check that
     * can only pass looks exactly like a check that passed.
     */
    public function test_the_fixture_can_tell_the_two_currencies_apart(): void
    {
        $this->seedCatalogue();

        $usd = HomePageCache::buildPayload(null, null, false, 'USD')['cheapestPaid']['monthly'];
        $inr = HomePageCache::buildPayload(null, null, false, 'INR')['cheapestPaid']['monthly'];

        $this->assertNotSame(
            $usd['formatted'],
            $inr['formatted'],
            'the two currencies render the same string, so the currency assertion above proves nothing'
        );
        $this->assertSame(700, (int) $usd['amount_minor']);
        $this->assertSame(16700, (int) $inr['amount_minor']);
    }

    /**
     * Which plan the catalogue starts at does not change with the currency.
     *
     * The minimum is ranked on USD precisely so it cannot: ranking on the
     * payload's own currency would drop any plan with no row in it (resolved
     * zero, read as free) out of the comparison, and the homepage would name
     * a different entry plan to an Indian visitor than to an American one.
     */
    public function test_the_same_plan_is_the_starting_point_in_every_currency(): void
    {
        $this->seedCatalogue();

        $names = [];
        foreach (HomePageCache::CURRENCIES as $currency) {
            $names[$currency] = HomePageCache::buildPayload(null, null, false, $currency)['cheapestPaid']['name'] ?? null;
        }

        $this->assertSame('Lite', $names['USD']);
        $this->assertCount(1, array_unique($names), 'the homepage names a different entry plan per currency: ' . json_encode($names));
    }

    /**
     * A paid plan stays paid in a currency it has no price row in.
     *
     * `buildPrice` is explicit about resolving an absent row to zero rather
     * than falling back to USD, so deciding freeness from the payload's own
     * currency would promote that plan into the free tile. Freeness is a fact
     * about the plan, and /pricing already decides it on USD.
     */
    public function test_a_plan_with_no_row_in_this_currency_is_not_free(): void
    {
        Plan::query()->update(['is_archived' => true, 'status' => 'inactive']);
        $this->plan('Starter', 0, 0, false, 0);
        // USD only -- no INR row at all.
        $usdOnly = Plan::create([
            'name' => 'Usd Only', 'slug' => 'cur-usd-only', 'description' => 'usd only',
            'status' => 'active', 'is_archived' => false, 'is_popular' => true,
            'sort_order' => 1, 'features' => [],
        ]);
        PricingResolver::upsertFromMinor($usdOnly, 'USD', 'monthly', 1500);

        $payload = HomePageCache::buildPayload(null, null, false, 'INR');

        $card = collect($payload['plans'])->firstWhere('name', 'Usd Only');
        $this->assertNotNull($card, 'the USD-only plan fell out of the INR payload entirely');
        $this->assertFalse(
            (bool) $card['is_free'],
            'a paid plan with no INR row was read as free, and would render in the free tile'
        );
    }

    /**
     * Every home partial that prints a plan price follows the footer.
     *
     * The footer is the only currency control a public page has, and it
     * speaks by dispatching `inme-currency` on the window. A price-bearing
     * section with no listener is a figure the visitor cannot change, which
     * is the bug: the listener existed, in a partial this design does not
     * render.
     */
    public function test_every_price_bearing_home_partial_follows_the_footer(): void
    {
        $partials = $this->pricePrintingPartials();

        // The collector has to actually collect, or this test is a no-op that
        // reads like a pass.
        $this->assertNotEmpty($partials, 'no home partial appears to print a plan price; the collector is broken');
        $this->assertArrayHasKey(
            'compact-conversion.blade.php',
            $partials,
            'the live design\'s pricing section was not collected, which is the one that was broken'
        );

        $deaf = [];
        foreach ($partials as $name => $source) {
            if (! str_contains($source, 'inme-currency')) {
                $deaf[] = $name;
            }
        }

        $this->assertSame([], $deaf, sprintf(
            "These home partials print a plan price but never hear the footer's currency\n"
            . "switcher, so the figure cannot be changed from the only control the page has:\n\n%s",
            implode("\n", $deaf)
        ));
    }

    /**
     * And the control for THAT: a partial with no price in it is not swept up
     * by the collector, so the assertion above is about price-bearing
     * partials rather than about every file in the directory.
     */
    public function test_the_collector_ignores_partials_that_print_no_price(): void
    {
        $partials = $this->pricePrintingPartials();

        $this->assertArrayNotHasKey('compact-trust.blade.php', $partials);
        $this->assertArrayNotHasKey('hero.blade.php', $partials);
    }

    /**
     * The live teaser reads the per-currency figure, not the single-currency
     * key the warmer baked.
     */
    public function test_the_live_teaser_reads_the_per_currency_price(): void
    {
        $source = file_get_contents(resource_path('views/home/partials/compact-conversion.blade.php'));

        $this->assertStringNotContainsString(
            "{{ \$publicPaid['monthly']['formatted'] }}",
            $source,
            'the teaser still prints the payload-wide monthly figure, which is whatever '
            . 'currency the shared cache was warmed in'
        );
        $this->assertStringContainsString(
            "\$publicPaid['prices']",
            $source,
            'the teaser does not read the per-currency prices the payload carries'
        );
    }

    /**
     * Stale payloads do not survive the deploy.
     *
     * A payload written before this change holds one currency's prices under
     * both keys. Without a prefix bump the fix ships and the bug stays live
     * for the rest of the TTL -- which has happened on this key before, twice,
     * which is why the constant's docblock keeps restating it.
     */
    public function test_the_payload_cache_was_retired(): void
    {
        $this->assertStringNotContainsString(
            ':v4:',
            HomePageCache::ANON_PAYLOAD_PREFIX,
            'the payload is built differently but the cache prefix did not move, so the '
            . 'wrong-currency payloads keep being served'
        );
    }
}
