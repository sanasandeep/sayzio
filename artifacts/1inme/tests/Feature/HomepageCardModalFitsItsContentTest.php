<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The card modal is as wide as what it holds.
 *
 * Two kinds of modal open from the homepage's expandable cards. A card that
 * ships its own `<template class="xc-detail">` gets a real two-column panel
 * and fills 1080px. A card without one opens a cleaned-up clone of itself,
 * and everything in a clone is deliberately capped at 660px -- a card row
 * stretched to 1080px puts 900px of nothing between a label and its value.
 *
 * Both used the same 1080px panel, so the clone kind reserved 1080 and used
 * 660, leaving a column of nothing down the right. The content also sat
 * off-centre in its own box, because `.xc-body` adds a further 44px of
 * padding on the right and nothing on the left: measured gutters were 45px
 * left and 89px right.
 *
 * Reported by Sana as "spaces and design not good" on the Themes modal.
 *
 * Measured after the change, at 1440 / 1024 / 390, every card:
 *
 *   [clone]  Themes & design controls   panel 748   gutters 45/45
 *   [clone]  Mobile-first by default    panel 748   gutters 45/45
 *   [detail] Performance Coach          panel 1080  gutters 45/45
 *
 * This guards the wiring that produces that, which is what an edit would
 * break: the clone path has to mark its panel, and the marked panel has to
 * be narrowed and its uneven padding removed.
 */
class HomepageCardModalFitsItsContentTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(
            resource_path('views/home/partials/expandable-cards.blade.php')
        );
    }

    public function test_the_cloned_card_modal_is_marked_as_one(): void
    {
        $source = $this->source();

        $this->assertStringContainsString(
            "modal.className = 'xc-modal xc-modal--clone';",
            $source,
            'the clone path no longer marks its panel, so it falls back to the 1080px one '
            . 'and shows 660px of content in it'
        );

        // The detail path must NOT be marked -- it is the one that earns 1080.
        $detailAt = strpos($source, "host.classList.add('xc-body--detail')");
        $this->assertNotFalse($detailAt, 'the detail path is gone from this partial');

        $detailShell = substr($source, max(0, $detailAt - 2000), 2000);
        $this->assertStringNotContainsString(
            'xc-modal--clone',
            $detailShell,
            'the detail modal is being narrowed too; its two-column layout needs the full width'
        );
    }

    public function test_the_marked_panel_is_narrowed_and_evenly_padded(): void
    {
        $source = $this->source();

        $this->assertMatchesRegularExpression(
            '/\.xc-modal--clone\s*\{[^}]*width:\s*min\(\s*\d+px/',
            $source,
            'the clone panel has no width of its own, so it is 1080px again with a hole in it'
        );

        $this->assertMatchesRegularExpression(
            '/\.xc-modal--clone\s+\.xc-body\s*\{[^}]*padding-right:\s*0/',
            $source,
            "the clone panel's extra right padding is back; the content sits off-centre in its own box"
        );
    }

    /**
     * The 660px cap is the reason the panel is narrow, so the two belong
     * together. If the cap goes, this test's premise goes with it and the
     * narrow panel becomes wrong rather than right.
     */
    public function test_the_clone_content_is_still_capped(): void
    {
        $this->assertStringContainsString(
            '.xc-body .flex-col > *:not([data-expand-more]) { max-width: 660px; }',
            $this->source(),
            'the 660px cap on cloned card content is gone -- the narrowed panel now clips it '
            . 'instead of fitting it'
        );
    }
}
