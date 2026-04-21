<?php

namespace Tests\Feature\Analytics;

use App\Modules\User\Controllers\LinkController;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Covers the `?lang_base=...` analytics filter on the link analytics page.
 *
 * The filter normalizes stored locales (mixed case, `-` and `_` separators)
 * and matches every locale that shares the requested base language. Invalid
 * codes must be silently dropped instead of filtering anything.
 *
 * The production query uses Postgres-only SQL (`SPLIT_PART`, `DATE_TRUNC`,
 * `TO_CHAR`); the test suite runs on SQLite, so we register lightweight UDFs
 * that mirror the Postgres semantics for the small dataset under test.
 *
 * We invoke the controller's `show()` directly and read the View's data —
 * this lets us assert on the filtered totals and per-card breakdown
 * collections without rendering the full Blade template (which has many
 * unrelated dependencies).
 */
class BaseLanguageFilterTest extends AnalyticsTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->registerSqliteAnalyticsUdfs();
    }

    /**
     * Register PHP UDFs on the active SQLite connection so the analytics
     * queries — which lean on Postgres functions — can run unchanged.
     * No-op if the connection isn't SQLite (e.g. a CI env wired to Postgres).
     */
    private function registerSqliteAnalyticsUdfs(): void
    {
        $pdo = DB::connection()->getPdo();
        if (DB::connection()->getDriverName() !== 'sqlite') {
            return;
        }

        $pdo->sqliteCreateFunction('SPLIT_PART', function ($str, $delim, $n) {
            $parts = explode((string) $delim, (string) $str);
            $idx = ((int) $n) - 1;
            return $parts[$idx] ?? '';
        }, 3);

        $pdo->sqliteCreateFunction('DATE_TRUNC', function ($unit, $ts) {
            $t = is_numeric($ts) ? (int) $ts : strtotime((string) $ts);
            if ($t === false) return $ts;
            return match (strtolower((string) $unit)) {
                'day'   => date('Y-m-d 00:00:00', $t),
                'week'  => date('Y-m-d 00:00:00', $t - ((int) date('w', $t)) * 86400),
                'month' => date('Y-m-01 00:00:00', $t),
                'year'  => date('Y-01-01 00:00:00', $t),
                default => (string) $ts,
            };
        }, 2);

        $pdo->sqliteCreateFunction('TO_CHAR', function ($ts, $fmt) {
            $t = is_numeric($ts) ? (int) $ts : strtotime((string) $ts);
            if ($t === false) return $ts;
            $php = strtr((string) $fmt, ['YYYY' => 'Y', 'MM' => 'm', 'DD' => 'd']);
            return date($php, $t);
        }, 2);
    }

    /**
     * Invoke `LinkController::show` directly and return the View's data.
     * Bypasses Blade rendering so we can assert on raw filter outputs.
     */
    private function callShow(User $user, Link $link, array $query = []): array
    {
        $this->actingAs($user);
        $request = Request::create('/user/links/' . $link->id, 'GET', $query);
        $request->setUserResolver(fn () => $user);

        /** @var LinkController $controller */
        $controller = $this->app->make(LinkController::class);
        $view = $controller->show($request, $link);

        return $view->getData();
    }

    private function makeClick(Link $link, string $language, array $extras = []): LinkClick
    {
        return LinkClick::create(array_merge([
            'link_id'      => $link->id,
            'language'     => $language,
            'browser'      => 'Chrome',
            'os'           => 'macOS',
            'country_code' => 'US',
            'device_type'  => 'desktop',
            'source'       => 'web',
            'ip_address'   => '10.0.0.' . random_int(1, 254),
            'clicked_at'   => now()->subHours(2),
        ], $extras));
    }

    public function test_lang_base_buckets_mixed_case_and_underscore_locales(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // Three locales that share the "en" base, in three different shapes.
        $this->makeClick($link, 'en-US');
        $this->makeClick($link, 'EN-GB');
        $this->makeClick($link, 'en_CA');
        // Noise — must be excluded.
        $this->makeClick($link, 'fr-FR');
        $this->makeClick($link, 'de-DE');

        $data = $this->callShow($user, $link, ['lang_base' => 'en']);

        $this->assertSame('en', $data['baseLanguageFilter']);
        $this->assertSame(3, $data['totalInRange']);
    }

    public function test_lang_base_is_case_insensitive_on_input(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $this->makeClick($link, 'en-US');
        $this->makeClick($link, 'fr-FR');

        $data = $this->callShow($user, $link, ['lang_base' => 'EN']);

        // Input is normalized to lowercase before being applied.
        $this->assertSame('en', $data['baseLanguageFilter']);
        $this->assertSame(1, $data['totalInRange']);
    }

    public function test_invalid_lang_base_value_is_silently_dropped(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $this->makeClick($link, 'en-US');
        $this->makeClick($link, 'fr-FR');

        // 4 letters is outside the 2-3 letter window — must be ignored.
        $data = $this->callShow($user, $link, ['lang_base' => 'zzzz']);

        $this->assertNull($data['baseLanguageFilter']);
        $this->assertSame(2, $data['totalInRange']);
    }

    public function test_unknown_3_letter_lang_base_passes_validation_but_matches_nothing(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $this->makeClick($link, 'en-US');
        $this->makeClick($link, 'fr-FR');

        // `zzz` is structurally valid (2-3 lowercase letters) so it survives
        // the regex guard, but no stored locale buckets to it, so the
        // dashboard ends up filtered down to zero clicks rather than
        // silently widening to "all clicks".
        $data = $this->callShow($user, $link, ['lang_base' => 'zzz']);

        $this->assertSame('zzz', $data['baseLanguageFilter']);
        $this->assertSame(0, $data['totalInRange']);
        $this->assertSame(0, $data['browserStats']->count());
    }

    public function test_lang_base_with_digits_or_punctuation_is_dropped(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $this->makeClick($link, 'en-US');
        $this->makeClick($link, 'fr-FR');

        $data = $this->callShow($user, $link, ['lang_base' => 'en-US']);

        $this->assertNull($data['baseLanguageFilter']);
        $this->assertSame(2, $data['totalInRange']);
    }

    public function test_breakdown_cards_respect_base_language_filter(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // Two English clicks across distinct browser/OS/country/device/source.
        $this->makeClick($link, 'en-US', [
            'browser' => 'Chrome', 'os' => 'macOS', 'country_code' => 'US',
            'device_type' => 'desktop', 'source' => 'web',
        ]);
        $this->makeClick($link, 'en_GB', [
            'browser' => 'Safari', 'os' => 'iOS', 'country_code' => 'GB',
            'device_type' => 'mobile', 'source' => 'mobile_app',
        ]);
        // French noise — every breakdown card must hide these dimensions
        // when the base-language filter is active.
        $this->makeClick($link, 'fr-FR', [
            'browser' => 'Firefox', 'os' => 'Windows', 'country_code' => 'FR',
            'device_type' => 'tablet', 'source' => 'web',
        ]);

        $data = $this->callShow($user, $link, ['lang_base' => 'en']);

        $this->assertSame(2, $data['totalInRange']);

        $browsers = $data['browserStats']->pluck('browser')->all();
        $this->assertEqualsCanonicalizing(['Chrome', 'Safari'], $browsers);
        $this->assertNotContains('Firefox', $browsers);

        $osList = $data['osStats']->pluck('os')->all();
        $this->assertEqualsCanonicalizing(['macOS', 'iOS'], $osList);
        $this->assertNotContains('Windows', $osList);

        $countries = $data['countryStats']->pluck('country_code')->all();
        $this->assertEqualsCanonicalizing(['US', 'GB'], $countries);
        $this->assertNotContains('FR', $countries);

        $devices = $data['deviceStats']->pluck('device_type')->all();
        $this->assertEqualsCanonicalizing(['desktop', 'mobile'], $devices);
        $this->assertNotContains('tablet', $devices);

        $sources = $data['sourceStats']->pluck('source')->all();
        $this->assertEqualsCanonicalizing(['web', 'mobile_app'], $sources);
        // Sanity: each filtered source row counts only the English clicks.
        $this->assertSame(2, (int) $data['sourceStats']->sum('count'));
    }

    public function test_lang_base_only_matches_base_segment_not_substring(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // "ene" must NOT match "en" — the filter splits on `-`/`_`, not substring.
        $this->makeClick($link, 'ene-XX');
        $this->makeClick($link, 'en-US');

        $data = $this->callShow($user, $link, ['lang_base' => 'en']);

        $this->assertSame(1, $data['totalInRange']);
    }
}
