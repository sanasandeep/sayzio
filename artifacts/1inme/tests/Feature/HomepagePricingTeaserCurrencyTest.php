<?php

namespace Tests\Feature;

use App\Modules\Common\Support\HomePageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The pricing teaser quotes one currency at a time.
 *
 * It did not. The homepage showed Rs 167.00 on the Monthly tab and $3.33 on
 * Annual -- same plan, same visitor, one toggle apart.
 *
 * The monthly price read `cheapest[currency].monthly` and followed the
 * currency switcher. The annual price was rendered server-side in the Blade
 * from `$cheapestPaid['monthly']['currency']`, which is whatever currency this
 * SHARED anonymous cache happened to be warmed in -- so every visitor saw that
 * one currency on the annual tab, whatever their own.
 *
 * The payload is the fix: it now carries both cadences in both currencies, so
 * the view has something to switch to. These tests are about the payload,
 * because that is where the defect was; a view that reads a key which is not
 * there can only fall back.
 */
class HomepagePricingTeaserCurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The teaser's payload, built the way an anonymous visitor gets it.
     *
     * The currency argument is what the caller's own currency would be; it
     * does not narrow the payload, which is the whole point -- `cheapestPaid`
     * is supposed to carry every currency so the switcher has somewhere to
     * go. Asking for USD and then finding INR inside is the expected result.
     */
    private function cheapestPaid(): ?array
    {
        $payload = HomePageCache::buildPayload(null, 'monthly', false, 'USD');

        return $payload['cheapestPaid'] ?? null;
    }

    public function test_the_payload_carries_both_cadences_in_every_currency(): void
    {
        $cheapest = $this->cheapestPaid();

        if (! $cheapest) {
            $this->markTestSkipped('no paid plan in the catalogue to build a teaser from');
        }

        $missing = [];

        foreach (HomePageCache::CURRENCIES as $currency) {
            $prices = $cheapest['prices'][$currency] ?? null;

            if (! is_array($prices)) {
                $missing[] = "{$currency}: no prices at all";

                continue;
            }

            if (! array_key_exists('monthly', $prices)) {
                $missing[] = "{$currency}: no monthly";
            }

            // `annual` may be null -- a currency with no annual row -- but the
            // KEY has to exist, because its absence is what sent the view
            // looking for a server-rendered figure in the first place.
            if (! array_key_exists('annual', $prices)) {
                $missing[] = "{$currency}: no annual key";
            }
        }

        $this->assertSame([], $missing, sprintf(
            "The teaser payload does not carry every cadence in every currency, so the\n"
            . "annual tab has nothing to switch to and falls back to a figure baked in\n"
            . "whatever currency this shared cache was warmed in.\n\n%s",
            implode("\n", $missing)
        ));
    }

    /**
     * Every figure the teaser can show, in one currency, is in that currency.
     *
     * This is the actual user-visible promise: pick a currency, and nothing on
     * the card is priced in another one.
     */
    public function test_no_currency_mixes_with_another(): void
    {
        $cheapest = $this->cheapestPaid();

        if (! $cheapest) {
            $this->markTestSkipped('no paid plan in the catalogue to build a teaser from');
        }

        $wrong = [];

        foreach (HomePageCache::CURRENCIES as $currency) {
            foreach (['monthly', 'annual'] as $cadence) {
                $price = $cheapest['prices'][$currency][$cadence] ?? null;

                if (! is_array($price)) {
                    continue;
                }

                if (($price['currency'] ?? null) !== $currency) {
                    $wrong[] = sprintf(
                        '%s %s is priced in %s',
                        $currency,
                        $cadence,
                        $price['currency'] ?? '(none)'
                    );
                }
            }
        }

        $this->assertSame([], $wrong, "The teaser would show two currencies at once:\n" . implode("\n", $wrong));
    }

    /**
     * An annual price that exists carries its monthly equivalent.
     *
     * The headline says "/mo, from" on both tabs, so the annual tab needs a
     * per-month figure. `formatted` on an annual price is the YEARLY total --
     * showing that under a "/mo" label would overstate the price by twelve.
     */
    public function test_an_annual_price_carries_its_per_month_equivalent(): void
    {
        $cheapest = $this->cheapestPaid();

        if (! $cheapest) {
            $this->markTestSkipped('no paid plan in the catalogue to build a teaser from');
        }

        foreach (HomePageCache::CURRENCIES as $currency) {
            $annual = $cheapest['prices'][$currency]['annual'] ?? null;

            if (! is_array($annual)) {
                continue; // no annual row in this currency; the view falls back
            }

            $this->assertArrayHasKey(
                'per_month_formatted',
                $annual,
                "{$currency} has an annual price but no per-month equivalent, so the teaser "
                . 'would print a yearly total under a "/mo" label'
            );

            $this->assertGreaterThan(
                0,
                (int) ($annual['amount_minor'] ?? 0),
                "{$currency} annual is present but zero; it should have been dropped instead"
            );
        }
    }

    /**
     * And the cache prefix moved.
     *
     * A payload written before this change has no annual key. If the prefix
     * had stayed at v2 those payloads would keep being served, and the bug
     * would stay live for the rest of their TTL after a deploy that "fixed"
     * it.
     */
    public function test_the_payload_cache_was_retired(): void
    {
        $this->assertStringNotContainsString(
            ':v2:',
            HomePageCache::ANON_PAYLOAD_PREFIX,
            'the payload shape changed but the cache prefix did not, so stale payloads survive the deploy'
        );
    }
}
