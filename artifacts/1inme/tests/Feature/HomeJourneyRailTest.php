<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workflow rail is a tablist wearing different paint.
 *
 * Turning four tab buttons into four stops on a route is the kind of change
 * that silently costs keyboard and screen-reader users everything: the
 * styling is what you look at, and role/aria-controls/aria-selected/roving
 * tabindex are what actually make it work. compact-home-script drives the
 * section entirely off those attributes, so losing one breaks arrow-key
 * navigation with nothing visibly wrong.
 *
 * The second test is the point of the redesign: the record's stage chips
 * accumulate rather than swap, so by the last stop it still shows the
 * enquiry arriving and the invoice being paid. If that ever reverts to one
 * chip per stage the section is back to four disconnected cards.
 */
class HomeJourneyRailTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_rail_keeps_its_tablist_semantics(): void
    {
        $xpath = $this->homeXpath();

        $rail = $xpath->query('//*[@id="customer-workflow"]//*[@role="tablist"]');
        $this->assertSame(1, $rail->length, 'the rail must still be a tablist');

        $stops = $xpath->query('//*[@id="customer-workflow"]//*[@role="tab"]');
        $this->assertSame(4, $stops->length, 'four stops on the route');

        $selected = 0;
        foreach ($stops as $stop) {
            $controls = $stop->getAttribute('aria-controls');
            $this->assertNotSame('', $controls, 'every stop must name the panel it controls');
            $this->assertSame(
                1,
                $xpath->query('//*[@id="'.$controls.'" and @role="tabpanel"]')->length,
                "stop controls a panel that does not exist: {$controls}"
            );

            if ($stop->getAttribute('aria-selected') === 'true') {
                $selected++;
                $this->assertSame('0', $stop->getAttribute('tabindex'), 'the selected stop is the tab stop');
            } else {
                $this->assertSame('-1', $stop->getAttribute('tabindex'), 'unselected stops are skipped by Tab');
            }
        }

        $this->assertSame(1, $selected, 'exactly one stop starts selected');
    }

    public function test_the_record_carries_its_history_forward(): void
    {
        $xpath = $this->homeXpath();

        $counts = [];
        foreach (['customers', 'sales', 'delivery', 'support'] as $stage) {
            $counts[$stage] = $xpath->query(
                '//*[@id="workflow-panel-'.$stage.'"]//*[contains(@class,"ch-trail-chip")]'
            )->length;
        }

        $this->assertSame(
            [1, 2, 3, 4],
            array_values($counts),
            'the stage chips must accumulate along the journey, not swap: '.json_encode($counts)
        );

        // The newest chip is live; everything before it is done.
        foreach (['customers', 'sales', 'delivery', 'support'] as $stage) {
            $this->assertSame(
                1,
                $xpath->query('//*[@id="workflow-panel-'.$stage.'"]//*[contains(@class,"is-now")]')->length,
                "stage {$stage} must have exactly one live chip"
            );
        }
    }

    private function homeXpath(): \DOMXPath
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($this->get('/')->assertOk()->getContent());

        return new \DOMXPath($dom);
    }
}
