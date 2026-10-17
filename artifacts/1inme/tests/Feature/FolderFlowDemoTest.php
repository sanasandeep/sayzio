<?php

namespace Tests\Feature;

use App\Services\Planner\FolderFlowDemo;
use App\Services\Planner\FolderFlowNetwork;
use App\Services\Planner\FolderFlowStages;
use Tests\TestCase;

/**
 * The How-it-works view teaches the map by showing a full one. Sana asked for
 * it to match the real thing rather than look like a separate illustration:
 * "demo should match similar.. but not empty things.... show some value".
 *
 * Matching is the whole risk. An example that drifts teaches a map the user
 * will never see, and because every number in it is invented, nothing about
 * it can go wrong loudly. These guards are the ones that would otherwise go
 * unnoticed: same stations, same geometry, and never empty.
 */
class FolderFlowDemoTest extends TestCase
{
    /** Same stations, same places. The example is the map, with other numbers. */
    public function test_the_example_is_the_same_map_as_the_real_one(): void
    {
        $real = FolderFlowNetwork::stations();
        $demo = FolderFlowDemo::map()['stations'];

        $this->assertSame(array_keys($real), array_column($demo, 'kind'),
            'the example shows a different set of stations than the map it is teaching');

        foreach ($demo as $station) {
            $place = $real[$station['kind']];
            $this->assertSame($place['col'], $station['col'], "{$station['kind']} sits elsewhere in the example");
            $this->assertSame($place['row'], $station['row'], "{$station['kind']} sits elsewhere in the example");
            $this->assertSame($place['icon'], $station['icon'], "{$station['kind']} has a different icon in the example");
            $this->assertSame(FolderFlowNetwork::isAi($station['kind']), $station['ai'],
                "{$station['kind']} is drawn as AI in one place and not the other");
            $this->assertSame(FolderFlowNetwork::lineFor($station['kind']), $station['stage'],
                "{$station['kind']} is on a different line in the example");
        }
    }

    /**
     * The reason this view exists: a new folder is empty, and an empty map
     * teaches nothing about what a full one would tell you.
     */
    public function test_no_station_in_the_example_is_empty(): void
    {
        foreach (FolderFlowDemo::map()['stations'] as $station) {
            $this->assertGreaterThan(0, $station['count'],
                "{$station['kind']} is empty in the example, which is the one thing the example exists to avoid");
            $this->assertTrue($station['in_use'],
                "{$station['kind']} would draw dashed, so the example would show a half-used folder");
        }
    }

    /** Junctions and faces too: the lines and the people have to have something to show. */
    public function test_the_example_shows_connections_and_people(): void
    {
        $map = FolderFlowDemo::map();
        $this->assertNotEmpty($map['edges'], 'no junctions, so the example cannot show what one is');

        foreach ($map['edges'] as $edge) {
            $this->assertArrayHasKey($edge['from'], FolderFlowNetwork::stations(), 'a junction points at no station');
            $this->assertArrayHasKey($edge['to'], FolderFlowNetwork::stations(), 'a junction points at no station');
            $this->assertGreaterThan(0, $edge['count']);
        }

        $withFaces = array_filter($map['stations'], fn ($s) => $s['people'] !== []);
        $this->assertNotEmpty($withFaces, 'nobody worked anywhere, so the example cannot show what a face means');

        $activity = FolderFlowDemo::activity();
        $this->assertGreaterThan(0, $activity['counted']);
        $this->assertSame($activity['total'], $activity['counted'] + $activity['owner_changes'] + $activity['unattributed'],
            'the example should add up the same way the real figures do');
    }

    /**
     * The sample people must not be mistakable for workspace members. Negative
     * ids cannot collide with a real user, which also means the person filter
     * can never match one of them against real activity.
     */
    public function test_sample_people_cannot_be_mistaken_for_real_members(): void
    {
        foreach (FolderFlowDemo::activity()['people'] as $person) {
            $this->assertLessThan(0, $person['id'],
                'a sample person with a real-looking id could collide with a workspace member');
            $this->assertArrayNotHasKey('avatar_url', $person,
                'a sample person must not carry a real avatar');
        }
    }

    /** Every mark the map makes should be explained, or the view is incomplete. */
    public function test_the_explanations_cover_what_the_map_draws(): void
    {
        $text = mb_strtolower(implode(' ', array_merge(
            array_column(FolderFlowDemo::legend(), 'title'),
            array_column(FolderFlowDemo::legend(), 'body'),
        )));

        foreach (['station', 'rail', 'junction', 'ai', 'filter'] as $mark) {
            $this->assertStringContainsString($mark, $text,
                "the How-it-works view never explains what a {$mark} is");
        }

        $this->assertCount(count(FolderFlowStages::all()), array_filter(
            FolderFlowStages::all(),
            fn ($stage) => str_contains($text, mb_strtolower($stage['eyebrow'])),
        ), 'a stage is drawn on the example but never named in the explanations');
    }
}
