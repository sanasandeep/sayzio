<?php

namespace Tests\Feature;

use App\Modules\Common\Support\HomePageCache;
use App\Modules\Common\Support\PricingPageCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Guards the flush-vs-warm race: the scheduled `home:warm-caches` job
 * (every ~4 min, WARM_TTL 900s) could previously start building the plan
 * teaser / pricing catalogue just BEFORE an admin plan save committed, and
 * then re-cache pre-edit data over the flush — re-shadowing the edit for up
 * to one warm cadence.
 *
 * The fix is a versioned cache key: PricingPageCache::flush() bumps a
 * monotonic catalogue version that suffixes both the /pricing catalogue key
 * and the homepage anonymous plan-teaser keys. The warmer captures the
 * version before it starts building and pins its writes to it, so a flush
 * that lands mid-run retires those keys — readers on the bumped version take
 * a clean miss and rebuild fresh.
 *
 * These tests simulate the overlap deterministically by capturing the
 * version (as the warmer does at the top of HomePageCache::warm()),
 * flushing, and only then letting the "in-flight" warm write land.
 */
class PlanCacheFlushWarmRaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_flush_bumps_the_catalogue_version(): void
    {
        $before = PricingPageCache::version();
        PricingPageCache::flush();
        $this->assertSame($before + 1, PricingPageCache::version());

        // Version changes the keys for BOTH plan-bearing surfaces.
        $this->assertNotSame(
            PricingPageCache::catalogKey($before),
            PricingPageCache::catalogKey()
        );
        $this->assertNotSame(
            HomePageCache::anonPayloadKey('USD', $before),
            HomePageCache::anonPayloadKey('USD')
        );
    }

    public function test_in_flight_pricing_warm_cannot_reshadow_a_plan_flush(): void
    {
        // The warm run captures the version BEFORE building (as
        // HomePageCache::warm() does)...
        $inFlightVersion = PricingPageCache::version();

        // ...an admin plan save lands while the build is in flight...
        PricingPageCache::flush();

        // ...and the warm run's write completes with pre-edit data.
        PricingPageCache::warm(HomePageCache::WARM_TTL, $inFlightVersion);

        // The write landed on the retired version's key only: readers (on
        // the bumped version) see a miss, never the stale payload.
        $this->assertNull(
            Cache::get(PricingPageCache::catalogKey()),
            'A warm run that started before the flush must not repopulate the current catalogue key.'
        );
        $this->assertNotNull(
            Cache::get(PricingPageCache::catalogKey($inFlightVersion)),
            'The stale write lands on the retired version key (harmless, expires with its TTL).'
        );
    }

    public function test_in_flight_home_payload_warm_cannot_reshadow_a_plan_flush(): void
    {
        $inFlightVersion = PricingPageCache::version();

        // Admin plan save lands mid-build.
        PricingPageCache::flush();

        // The in-flight warm writes the (pre-edit) home teaser payloads,
        // pinned to the version it captured before building — exactly what
        // HomePageCache::warm() does via anonPayloadKey($cur, $planVersion).
        foreach (HomePageCache::CURRENCIES as $currency) {
            Cache::put(
                HomePageCache::anonPayloadKey($currency, $inFlightVersion),
                '{"stale":true}',
                HomePageCache::WARM_TTL
            );
        }

        // Readers resolve the CURRENT version and miss.
        foreach (HomePageCache::CURRENCIES as $currency) {
            $this->assertNull(
                Cache::get(HomePageCache::anonPayloadKey($currency)),
                "Home teaser for {$currency} must not be re-shadowed by an overlapping warm run."
            );
        }
    }

    public function test_next_warm_run_repopulates_the_current_version_keys(): void
    {
        PricingPageCache::flush();

        // A warm run that STARTS after the flush uses the bumped version and
        // repopulates the keys readers actually consult.
        PricingPageCache::warm(HomePageCache::WARM_TTL);

        $this->assertNotNull(Cache::get(PricingPageCache::catalogKey()));
    }
}
