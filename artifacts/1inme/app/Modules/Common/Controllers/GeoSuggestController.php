<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Server-side geocoding suggestion proxy for the shared map pin picker.
 *
 * Public Nominatim forbids client-side autocomplete and caps usage at
 * ~1 request/second, so the browser must never call it per keystroke.
 * Instead the picker's suggestPlaces() hits this authenticated, per-user
 * throttled endpoint, which:
 *   - serves repeated queries from a long-lived cache (place data is
 *     effectively static), and
 *   - gates all cache-miss upstream calls behind a single global
 *     1-request/second limiter so the whole platform combined stays
 *     within the provider's policy. When the gate is busy the request
 *     simply returns no suggestions instead of queueing.
 */
class GeoSuggestController extends Controller
{
    private const CACHE_TTL = 60 * 60 * 24 * 7; // 7 days — places don't move
    private const UPSTREAM = 'https://nominatim.openstreetmap.org/search';

    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 3 || mb_strlen($q) > 200 || Str::startsWith(Str::lower($q), ['http://', 'https://'])) {
            return response()->json(['suggestions' => []]);
        }

        $key = 'geo:suggest:' . sha1(Str::lower($q));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return response()->json(['suggestions' => $cached]);
        }

        // Global (cross-user) upstream gate: at most 1 Nominatim call per
        // second platform-wide. Busy gate → empty result, never a queue.
        // (Captured by reference because RateLimiter::attempt() normalizes
        // falsy callback returns — [] / null — to true.)
        $rows = null;
        RateLimiter::attempt('nominatim-suggest-upstream', 1, function () use ($q, &$rows) {
            try {
                $resp = Http::timeout(5)
                    ->withHeaders([
                        'User-Agent' => '1INME-MapPicker/1.0 (+https://1inme.com)',
                        'Accept' => 'application/json',
                    ])
                    ->get(self::UPSTREAM, [
                        'format' => 'jsonv2',
                        'limit' => 5,
                        'q' => $q,
                    ]);

                if (! $resp->ok()) {
                    return; // don't cache failures
                }

                $rows = collect($resp->json())
                    ->filter(fn ($row) => is_array($row) && isset($row['place_id'], $row['display_name'], $row['lat'], $row['lon']))
                    ->map(fn ($row) => [
                        'id' => $row['place_id'],
                        'label' => (string) $row['display_name'],
                        'lat' => (string) $row['lat'],
                        'lng' => (string) $row['lon'],
                    ])
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                // leave $rows null — treated as failure below
            }
        }, decaySeconds: 1);

        if (! is_array($rows)) {
            // Gate busy or upstream failure — respond empty without caching
            // so the next (throttle-spaced) attempt can retry.
            return response()->json(['suggestions' => []]);
        }

        Cache::put($key, $rows, self::CACHE_TTL);

        return response()->json(['suggestions' => $rows]);
    }
}
