<?php

namespace Tests\Unit;

use App\Modules\Common\Support\SitePagesContent;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit coverage for SitePagesContent::normalizeContactExtra map
 * clamping. The Feature suite already round-trips a representative
 * payload end-to-end, but this isolates the math: lat/lng/zoom must
 * always land inside their valid windows so the public /contact page
 * never builds an OpenStreetMap bbox / Leaflet centre that the tile
 * server (or `cos(deg2rad($lat))` in contact.blade.php) can't sanely
 * render. Lives in tests/Unit so it stays fast and runnable without a
 * database — a future regression in the clamp is caught even if the
 * pgsql Feature suite is skipped.
 */
class SitePagesContentNormalizeContactExtraTest extends TestCase
{
    public function test_lat_lng_zoom_at_exact_upper_bounds_pass_through(): void
    {
        $out = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => 90.0, 'lng' => 180.0, 'zoom' => 19],
        ]);
        $this->assertSame(90.0,  $out['map']['lat']);
        $this->assertSame(180.0, $out['map']['lng']);
        $this->assertSame(19,    $out['map']['zoom']);
    }

    public function test_lat_lng_zoom_at_exact_lower_bounds_pass_through(): void
    {
        $out = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => -90.0, 'lng' => -180.0, 'zoom' => 1],
        ]);
        $this->assertSame(-90.0,  $out['map']['lat']);
        $this->assertSame(-180.0, $out['map']['lng']);
        $this->assertSame(1,      $out['map']['zoom']);
    }

    public function test_values_just_past_upper_bounds_get_clamped_down(): void
    {
        // One ULP / one unit past the bound is the most common failure
        // mode (off-by-one in the validator). Clamp must still catch it.
        $out = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => 90.0001, 'lng' => 180.0001, 'zoom' => 20],
        ]);
        $this->assertSame(90.0,  $out['map']['lat']);
        $this->assertSame(180.0, $out['map']['lng']);
        $this->assertSame(19,    $out['map']['zoom']);
    }

    public function test_values_just_past_lower_bounds_get_clamped_up(): void
    {
        $out = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => -90.0001, 'lng' => -180.0001, 'zoom' => 0],
        ]);
        $this->assertSame(-90.0,  $out['map']['lat']);
        $this->assertSame(-180.0, $out['map']['lng']);
        $this->assertSame(1,      $out['map']['zoom']);
    }

    public function test_extreme_out_of_range_values_clamp_to_bounds(): void
    {
        // Numbers wildly past the bounds (e.g. a copy/paste accident
        // submitting 9999) must still land at the bound, never NaN /
        // +Inf / a wrap-around.
        $high = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => 9999.0, 'lng' => 9999.0, 'zoom' => 9999],
        ]);
        $this->assertSame(90.0,  $high['map']['lat']);
        $this->assertSame(180.0, $high['map']['lng']);
        $this->assertSame(19,    $high['map']['zoom']);

        $low = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => -9999.0, 'lng' => -9999.0, 'zoom' => -9999],
        ]);
        $this->assertSame(-90.0,  $low['map']['lat']);
        $this->assertSame(-180.0, $low['map']['lng']);
        $this->assertSame(1,      $low['map']['zoom']);
    }

    public function test_numeric_string_inputs_are_coerced_then_clamped(): void
    {
        // HTML form submissions arrive as strings — ensure they're
        // accepted by the `is_numeric` check and still clamped.
        $out = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => '95.5', 'lng' => '-200', 'zoom' => '25'],
        ]);
        $this->assertSame(90.0,   $out['map']['lat']);
        $this->assertSame(-180.0, $out['map']['lng']);
        $this->assertSame(19,     $out['map']['zoom']);
    }

    public function test_non_numeric_inputs_fall_back_to_seeded_defaults(): void
    {
        // Anything `is_numeric` rejects (null, empty string, words)
        // must not silently become 0 — that would render an empty
        // ocean tile. Fall back to contactExtraDefault() instead.
        $defaults = SitePagesContent::contactExtraDefault();
        $out = SitePagesContent::normalizeContactExtra([
            'map' => ['lat' => 'banana', 'lng' => null, 'zoom' => ''],
        ]);
        $this->assertSame((float) $defaults['map']['lat'],  $out['map']['lat']);
        $this->assertSame((float) $defaults['map']['lng'],  $out['map']['lng']);
        $this->assertSame((int)   $defaults['map']['zoom'], $out['map']['zoom']);
    }

    public function test_missing_map_key_falls_back_to_seeded_defaults(): void
    {
        // The whole `map` sub-array can be absent (e.g. an old payload
        // shape). The normaliser must still emit a well-formed map
        // block so contact.blade.php's `(float)$map['lat']` never sees
        // a missing key.
        $defaults = SitePagesContent::contactExtraDefault();
        $out = SitePagesContent::normalizeContactExtra([]);
        $this->assertArrayHasKey('map', $out);
        $this->assertSame((float) $defaults['map']['lat'],  $out['map']['lat']);
        $this->assertSame((float) $defaults['map']['lng'],  $out['map']['lng']);
        $this->assertSame((int)   $defaults['map']['zoom'], $out['map']['zoom']);
        $this->assertSame('', $out['map']['label']);
    }
}
