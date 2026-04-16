<?php

namespace Tests\Feature\Analytics;

use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\PageSession;
use Illuminate\Support\Facades\DB;

class BackfillCoordinatesCommandTest extends AnalyticsTestCase
{
    public function test_command_fills_coordinates_from_seeded_cities_table(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        // Seed the reference cities table so the lookup resolves offline.
        DB::table('cities')->insert([
            [
                'country_code' => 'GB',
                'city_normalized' => 'london',
                'city_name' => 'London',
                'latitude' => 51.50853,
                'longitude' => -0.12574,
            ],
            [
                'country_code' => 'FR',
                'city_normalized' => 'paris',
                'city_name' => 'Paris',
                'latitude' => 48.85341,
                'longitude' => 2.3488,
            ],
        ]);

        // Rows that should be matched.
        $c1 = LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.10',
            'country_code' => 'GB', 'city' => 'London',
            'clicked_at' => now(),
        ]);
        $c2 = LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.11',
            'country_code' => 'FR', 'city' => 'Paris',
            'clicked_at' => now(),
        ]);

        // Row that should remain unmatched (unknown city).
        $c3 = LinkClick::create([
            'link_id' => $link->id,
            'ip_address' => '203.0.113.12',
            'country_code' => 'ZZ', 'city' => 'Nowhereville',
            'clicked_at' => now(),
        ]);

        $s1 = PageSession::create([
            'link_id' => $link->id,
            'session_id' => str_repeat('a', 36),
            'ip_address' => '203.0.113.20',
            'country_code' => 'GB', 'city' => 'London',
            'started_at' => now(),
            'last_seen_at' => now(),
            'duration_seconds' => 0,
        ]);

        $this->artisan('analytics:backfill-coords', ['--no-geoip' => true])
            ->assertExitCode(0);

        $c1->refresh(); $c2->refresh(); $c3->refresh(); $s1->refresh();

        $this->assertEqualsWithDelta(51.50853, (float) $c1->latitude, 0.0001);
        $this->assertEqualsWithDelta(-0.12574, (float) $c1->longitude, 0.0001);
        $this->assertEqualsWithDelta(48.85341, (float) $c2->latitude, 0.0001);
        $this->assertEqualsWithDelta(2.3488,   (float) $c2->longitude, 0.0001);

        $this->assertNull($c3->latitude);
        $this->assertNull($c3->longitude);

        $this->assertEqualsWithDelta(51.50853, (float) $s1->latitude, 0.0001);
        $this->assertEqualsWithDelta(-0.12574, (float) $s1->longitude, 0.0001);
    }

    public function test_command_is_idempotent_when_nothing_is_missing(): void
    {
        $user = $this->makeUser();
        $link = $this->makeLink($user);

        LinkClick::create([
            'link_id' => $link->id,
            'country_code' => 'GB', 'city' => 'London',
            'latitude' => 51.50853, 'longitude' => -0.12574,
            'clicked_at' => now(),
        ]);

        $this->artisan('analytics:backfill-coords', ['--no-geoip' => true])
            ->expectsOutputToContain('nothing to backfill')
            ->assertExitCode(0);
    }
}
