<?php

namespace Tests\Feature\Analytics;

use App\Modules\User\Models\LinkClick;
use Illuminate\Support\Facades\DB;

class HeatmapEndpointTest extends AnalyticsTestCase
{
    public function test_owner_receives_own_heatmap_data(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeLink($owner);

        // Two clicks at the same coordinates, one at another.
        foreach ([
            ['lat' => 51.50853, 'lng' => -0.12574, 'city' => 'London', 'cc' => 'GB'],
            ['lat' => 51.50853, 'lng' => -0.12574, 'city' => 'London', 'cc' => 'GB'],
            ['lat' => 48.85341, 'lng' => 2.3488,  'city' => 'Paris',  'cc' => 'FR'],
        ] as $c) {
            LinkClick::create([
                'link_id' => $link->id,
                'ip_address' => '203.0.113.10',
                'country_code' => $c['cc'],
                'city' => $c['city'],
                'latitude' => $c['lat'],
                'longitude' => $c['lng'],
                'clicked_at' => now(),
            ]);
        }

        // A click with no coordinates should not appear in the heatmap.
        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.11',
            'clicked_at' => now(),
        ]);

        // Another user's link + clicks: these must NOT leak into the
        // owner's heatmap response.
        $otherUser = $this->makeUser();
        $otherLink = $this->makeLink($otherUser);
        LinkClick::create([
            'link_id' => $otherLink->id,
            'ip_address' => '198.51.100.5',
            'country_code' => 'JP', 'city' => 'Tokyo',
            'latitude' => 35.68950, 'longitude' => 139.69171,
            'clicked_at' => now(),
        ]);
        LinkClick::create([
            'link_id' => $otherLink->id,
            'ip_address' => '198.51.100.6',
            'country_code' => 'JP', 'city' => 'Tokyo',
            'latitude' => 35.68950, 'longitude' => 139.69171,
            'clicked_at' => now(),
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('user.links.heatmap', $link));

        $response->assertOk();
        $json = $response->json();

        $this->assertSame('FeatureCollection', $json['type']);
        $this->assertCount(2, $json['features']);
        $this->assertCount(2, $json['points']);
        $this->assertSame(2, $json['meta']['point_count']);
        $this->assertSame(3, $json['meta']['total_clicks']);
        $this->assertSame(3, $json['meta']['shown_clicks']);
        $this->assertSame(2, $json['meta']['max_weight']);

        // The busiest hotspot is first.
        $top = $json['features'][0]['properties'];
        $this->assertSame(2, $top['count']);
        $this->assertSame('London', $top['city']);
        $this->assertSame('GB', $top['country_code']);

        // Explicitly assert that the other user's clicks did not leak in.
        $cities = array_column(array_column($json['features'], 'properties'), 'city');
        $this->assertNotContains('Tokyo', $cities);
    }

    public function test_heatmap_falls_back_to_city_lookup_for_rows_without_coordinates(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeLink($owner);

        // Seed the offline city reference table so the second pass can
        // resolve (city, country_code) → coordinates without the CSV.
        DB::table('cities')->insert([
            [
                'country_code' => 'DE',
                'city_normalized' => 'berlin',
                'city_name' => 'Berlin',
                'latitude' => 52.52437,
                'longitude' => 13.41053,
            ],
            [
                'country_code' => 'ES',
                'city_normalized' => 'madrid',
                'city_name' => 'Madrid',
                'latitude' => 40.4165,
                'longitude' => -3.70256,
            ],
        ]);

        // One exact-coordinate hotspot (2 clicks, London).
        foreach (range(1, 2) as $_) {
            LinkClick::create([
                'link_id' => $link->id,
                'ip_address' => '203.0.113.10',
                'country_code' => 'GB', 'city' => 'London',
                'latitude' => 51.50853, 'longitude' => -0.12574,
                'clicked_at' => now(),
            ]);
        }

        // Three city-only rows in Berlin — should collapse to one pin
        // weighted 3 via the CityLookupService fallback.
        foreach (range(1, 3) as $_) {
            LinkClick::create([
                'link_id' => $link->id,
                'ip_address' => '203.0.113.20',
                'country_code' => 'DE', 'city' => 'Berlin',
                'clicked_at' => now(),
            ]);
        }

        // One city-only row in Madrid.
        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.21',
            'country_code' => 'ES', 'city' => 'Madrid',
            'clicked_at' => now(),
        ]);

        // One city-only row that CANNOT be resolved by the lookup
        // table — must stay silently dropped and not count toward totals.
        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.22',
            'country_code' => 'ZZ', 'city' => 'Nowhereville',
            'clicked_at' => now(),
        ]);

        // A click with neither coords nor city is also dropped.
        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.23',
            'clicked_at' => now(),
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('user.links.heatmap', $link));

        $response->assertOk();
        $json = $response->json();

        // 3 pins: London (exact), Berlin (approx), Madrid (approx).
        $this->assertCount(3, $json['features']);
        $this->assertSame(3, $json['meta']['point_count']);
        // 2 London + 3 Berlin + 1 Madrid = 6 clicks on the map.
        $this->assertSame(6, $json['meta']['total_clicks']);
        $this->assertSame(6, $json['meta']['shown_clicks']);
        // Busiest hotspot is now Berlin (3) ahead of London (2).
        $this->assertSame(3, $json['meta']['max_weight']);

        $props = array_column($json['features'], 'properties');
        $byCity = collect($props)->keyBy('city');

        $this->assertSame(3, $byCity['Berlin']['count']);
        $this->assertTrue($byCity['Berlin']['approximate']);
        $this->assertEqualsWithDelta(52.52437, $byCity['Berlin']['lat'], 0.0001);
        $this->assertEqualsWithDelta(13.41053, $byCity['Berlin']['lng'], 0.0001);

        $this->assertSame(1, $byCity['Madrid']['count']);
        $this->assertTrue($byCity['Madrid']['approximate']);

        $this->assertSame(2, $byCity['London']['count']);
        $this->assertFalse($byCity['London']['approximate'] ?? false);

        // Busiest hotspot ordered first.
        $this->assertSame('Berlin', $json['features'][0]['properties']['city']);

        // Unresolved city-only rows must not appear anywhere.
        $cities = array_column($props, 'city');
        $this->assertNotContains('Nowhereville', $cities);
    }

    public function test_non_owner_receives_403(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $link = $this->makeLink($owner);

        LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.10',
            'country_code' => 'GB',
            'city' => 'London',
            'latitude' => 51.50853,
            'longitude' => -0.12574,
            'clicked_at' => now(),
        ]);

        $this->actingAs($other)
            ->getJson(route('user.links.heatmap', $link))
            ->assertStatus(403);
    }

    public function test_guest_cannot_access_heatmap(): void
    {
        $owner = $this->makeUser();
        $link = $this->makeLink($owner);

        $this->get(route('user.links.heatmap', $link))
            ->assertRedirect('/user/login');
    }
}
