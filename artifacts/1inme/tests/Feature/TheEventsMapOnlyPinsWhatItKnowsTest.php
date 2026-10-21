<?php

namespace Tests\Feature;

use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The directory lists events beside a map.
 *
 * A pin is a factual claim about where something is. An event whose
 * organiser never saved a location gets no pin: it is not dropped on
 * its city centre, not geocoded from the address string, and not
 * quietly left off the count. The panel says how many of the listed
 * events it could actually place.
 *
 * That is the whole point of these tests. The layout assertions are
 * incidental; this one is the rule.
 */
class TheEventsMapOnlyPinsWhatItKnowsTest extends TestCase
{
    use RefreshDatabase;

    private function publicEvent(string $title, ?float $lat, ?float $lng): Link
    {
        $owner = User::factory()->create(['onboarded_at' => now()->subMonths(6)]);

        $link = Link::create([
            'user_id'    => $owner->id,
            'type'       => 'ics',
            'alias'      => 'evt' . Str::random(8),
            'title'      => $title,
            'is_active'  => true,
            'visibility' => 'public',
        ]);

        IcsData::create([
            'link_id'    => $link->id,
            'event_name' => $title,
            'start_date' => now()->addDays(5)->toDateTimeString(),
            'end_date'   => now()->addDays(5)->addHours(3)->toDateTimeString(),
            'timezone'   => 'Asia/Kolkata',
            'location'   => 'Somewhere',
            'latitude'   => $lat,
            'longitude'  => $lng,
        ]);

        return $link->fresh();
    }

    private function directory(): string
    {
        return $this->get(route('events.index', [], false))->assertOk()->getContent();
    }

    /**
     * The points the map was actually handed.
     *
     * Captures the whole x-data argument rather than trying to match
     * balanced brackets: @js() html-escapes its output, and guessing at
     * that escaping is how the first version of this test failed twice.
     */
    private function mapPoints(string $body): array
    {
        if (!preg_match('~x-data="eventsResultsMap\((.*?)\)"~s', $body, $m)) {
            $near = '';
            $at = strpos($body, 'eventsResultsMap');
            if ($at !== false) {
                $near = substr($body, max(0, $at - 80), 320);
            }

            $this->fail(
                'could not read the map points out of the page. '
                . ($near === '' ? 'eventsResultsMap does not appear at all.' : "Saw: {$near}")
            );
        }

        $raw = $m[1];

        // Js::from() does not emit a JSON literal. It emits the
        // JavaScript expression JSON.parse('...'), with the payload
        // escaped as unicode (\u0022) inside a single-quoted JS string.
        // So there are two layers: unwrap the call, decode the string
        // literal, and only then is there JSON. Guessing at this cost
        // two red runs; the shape below came from printing the bytes.
        if (preg_match("~^JSON\\.parse\\('(.*)'\\)$~s", $raw, $w)) {
            $literal = json_decode('"' . str_replace('"', '\\"', $w[1]) . '"');

            if (is_string($literal)) {
                $decoded = json_decode($literal, true);

                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        // Fallbacks, in case that wrapper ever changes.
        $candidates = [
            $raw,
            html_entity_decode($raw, ENT_QUOTES),
            json_decode('"' . str_replace('"', '\\"', $raw) . '"') ?: '',
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            $decoded = json_decode($candidate, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $this->fail('the map argument did not decode as JSON under any known escaping. Raw: ' . mb_substr($raw, 0, 400));
    }

    public function test_an_event_with_no_saved_location_is_never_pinned(): void
    {
        $placed  = $this->publicEvent('Has A Location', 19.0760, 72.8777);
        $unknown = $this->publicEvent('Has No Location', null, null);

        $body = $this->directory();

        // Both are listed...
        $this->assertStringContainsString($placed->alias, $body);
        $this->assertStringContainsString($unknown->alias, $body);

        // ...but only the one we actually know about is on the map.
        $aliases = array_column($this->mapPoints($body), 'alias');

        $this->assertContains($placed->alias, $aliases);
        $this->assertNotContains($unknown->alias, $aliases,
            'an event with no coordinates was given a pin anyway');
    }

    public function test_every_point_carries_real_coordinates(): void
    {
        $this->publicEvent('Mumbai', 19.0760, 72.8777);
        $this->publicEvent('Nowhere', null, null);

        $points = $this->mapPoints($this->directory());

        $this->assertNotEmpty($points, 'nothing was placed at all');

        foreach ($points as $p) {
            $this->assertNotNull($p['lat'] ?? null);
            $this->assertNotNull($p['lng'] ?? null);

            // Real ranges, not "is it a number". A cast of null to float
            // lands at 0,0 -- a spot in the Atlantic that no event is at
            // -- which is exactly the shape this exists to catch.
            $this->assertGreaterThanOrEqual(-90,  $p['lat']);
            $this->assertLessThanOrEqual(90,      $p['lat']);
            $this->assertGreaterThanOrEqual(-180, $p['lng']);
            $this->assertLessThanOrEqual(180,     $p['lng']);

            $this->assertFalse(
                abs((float) $p['lat']) < 0.0001 && abs((float) $p['lng']) < 0.0001,
                'an unset coordinate was cast to null island and pinned there'
            );
        }
    }

    public function test_the_panel_says_how_many_it_could_place(): void
    {
        $this->publicEvent('Placed', 19.0760, 72.8777);
        $this->publicEvent('Unplaced', null, null);

        $body = $this->directory();

        // Two listed, one mappable -- the panel must not imply otherwise.
        $this->assertStringContainsString('1 of 2 on the map', $body);
        $this->assertStringContainsString('no saved location', $body);
    }

    public function test_the_list_and_the_map_sit_side_by_side(): void
    {
        $this->publicEvent('Anything', 19.0760, 72.8777);

        $body = $this->directory();

        $this->assertStringContainsString('evdir-split', $body);
        $this->assertStringContainsString('evdir-results', $body);
        $this->assertStringContainsString('evdir-map-panel', $body);
        // The card has to be addressable for hover to pair with its pin.
        $this->assertStringContainsString('data-event-alias', $body);
    }
}
