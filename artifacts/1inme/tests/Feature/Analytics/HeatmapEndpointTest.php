<?php

namespace Tests\Feature\Analytics;

use App\Modules\User\Models\LinkClick;

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
