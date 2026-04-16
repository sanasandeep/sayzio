<?php

namespace Tests\Feature\Analytics;

use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\PageSession;

class ClickTrackingCoordinatesTest extends AnalyticsTestCase
{
    public function test_link_click_persists_latitude_and_longitude(): void
    {
        $this->fakeGeoIp([
            'country_code' => 'GB',
            'city' => 'London',
            'latitude' => 51.50853,
            'longitude' => -0.12574,
        ]);

        $user = $this->makeUser();
        $link = $this->makeLink($user);

        $this->get('/' . $link->alias)->assertRedirect($link->long_url);

        $this->assertSame(1, LinkClick::where('link_id', $link->id)->count());

        $click = LinkClick::where('link_id', $link->id)->first();
        $this->assertNotNull($click->latitude);
        $this->assertNotNull($click->longitude);
        $this->assertEqualsWithDelta(51.50853, (float) $click->latitude, 0.0001);
        $this->assertEqualsWithDelta(-0.12574, (float) $click->longitude, 0.0001);
        $this->assertSame('GB', $click->country_code);
        $this->assertSame('London', $click->city);
    }

    public function test_page_session_persists_latitude_and_longitude(): void
    {
        $this->fakeGeoIp([
            'country_code' => 'US',
            'city' => 'New York',
            'latitude' => 40.71427,
            'longitude' => -74.00597,
        ]);

        $user = $this->makeUser();
        $link = $this->makeLink($user, ['type' => 'biolink', 'long_url' => null]);

        $response = $this->postJson('/' . $link->alias . '/track/session');
        $response->assertOk()->assertJsonStructure(['session_id']);

        $this->assertSame(1, PageSession::where('link_id', $link->id)->count());

        $session = PageSession::where('link_id', $link->id)->first();
        $this->assertNotNull($session->latitude);
        $this->assertNotNull($session->longitude);
        $this->assertEqualsWithDelta(40.71427, (float) $session->latitude, 0.0001);
        $this->assertEqualsWithDelta(-74.00597, (float) $session->longitude, 0.0001);
        $this->assertSame('US', $session->country_code);
        $this->assertSame('New York', $session->city);
    }
}
