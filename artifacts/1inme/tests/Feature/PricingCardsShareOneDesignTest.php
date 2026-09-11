<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The coin packages and the plans are one design, not two.
 *
 * They used to be two. On the same page, a plan was a 1.75rem card with a
 * tinted header band, a gradient icon chip, a bold name, an inset price panel
 * and a full-width pill; a coin pack was a 1rem glass panel with a small round
 * icon, a whispered grey name, a bare price in a footer rule and a little
 * right-aligned square button. Sana's note was "coin packages and pricing
 * plans both in similar design".
 *
 * They now share the chrome and differ only in colour -- blue for a
 * subscription, amber for the wallet, which is his call and worth keeping.
 *
 * This asserts the sharing rather than the appearance: both cards are built
 * from the same four classes, and the coin card carries the amber variant of
 * each. A future edit that rebuilds one of them from scratch will fail here
 * instead of quietly re-opening the gap.
 */
class PricingCardsShareOneDesignTest extends TestCase
{
    private function source(): string
    {
        return (string) file_get_contents(
            resource_path('views/public/pricing/plans.blade.php')
        );
    }

    /** The markup of one card, from its opening tag to the next card's. */
    private function cardMarkup(string $marker): string
    {
        $source = $this->source();
        $at = strpos($source, $marker);

        $this->assertNotFalse($at, "cannot find the {$marker} card in plans.blade.php");

        // Back up to the card's own root -- the nearest `plan-card` above the
        // name -- not merely the nearest `<div`, which since the badge moved
        // onto its own row is the icon/name row inside the header band.
        $root = strrpos(substr($source, 0, $at), 'class="plan-card');

        $this->assertNotFalse($root, "the {$marker} card has no .plan-card root above its name");

        // Run to where the next card begins rather than a fixed window: a
        // fixed one stopped short of the coin card's CTA and reported it
        // missing.
        $next = strpos($source, 'class="plan-card', $root + 1);

        return $next === false
            ? substr($source, $root)
            : substr($source, $root, $next - $root);
    }

    public function test_both_cards_are_built_from_the_same_classes(): void
    {
        $plan = $this->cardMarkup('$plan->name }}</h3>');
        $coin = $this->cardMarkup('$pkg->name }}</h3>');

        $shared = [
            'plan-card'      => 'the card chrome -- radius, inset highlights, shadow',
            'plan-band'      => 'the tinted header band',
            'plan-band-ico'  => 'the gradient icon chip',
            'plan-band-name' => 'the bold name',
            'plan-price'     => 'the inset price panel',
            'rounded-full'   => 'the pill CTA',
        ];

        $missing = [];

        foreach ($shared as $class => $what) {
            foreach (['plan' => $plan, 'coin' => $coin] as $which => $markup) {
                if (! str_contains($markup, $class)) {
                    $missing[] = "the {$which} card no longer uses .{$class} ({$what})";
                }
            }
        }

        $this->assertSame([], $missing, sprintf(
            "The two card kinds have drifted apart again.\n\n"
            . "They are meant to be one design in two colours -- same chrome, same\n"
            . "header, same price panel, same CTA -- differing only in hue.\n\n%s",
            implode("\n", $missing)
        ));
    }

    public function test_the_coin_card_carries_the_amber_variant(): void
    {
        $coin = $this->cardMarkup('$pkg->name }}</h3>');

        foreach (['plan-card grad-glow is-coin', 'plan-band is-coin', 'plan-band-ico is-coin', 'btn-coin'] as $needed) {
            $this->assertStringContainsString(
                $needed,
                $coin,
                "the coin card lost `{$needed}`, so it would render in the plans' blue"
            );
        }
    }

    /**
     * Every `is-coin` variant has a rule, in both themes.
     *
     * An amber variant class with no rule behind it renders as the blue base,
     * which is the one outcome this whole change exists to prevent.
     */
    public function test_the_amber_variants_are_defined_for_both_themes(): void
    {
        $source = $this->source();
        $missing = [];

        foreach (['.plan-card.is-coin', '.plan-band.is-coin', '.plan-band-ico.is-coin', '.btn-coin', '.coin-ink'] as $selector) {
            if (! str_contains($source, $selector . ' ') && ! str_contains($source, $selector . ',') && ! str_contains($source, $selector . '{')) {
                $missing[] = "{$selector} has no rule at all";
            }
        }

        // The light theme is the half that gets forgotten -- it is how the
        // head-to-head card came to render its own name at 1.17:1.
        foreach (['.plan-card.is-coin', '.plan-band.is-coin', '.coin-ink'] as $selector) {
            $pattern = '/html\.light-mode\s+' . preg_quote($selector, '/') . '(?![\w-])/';

            if (! preg_match($pattern, $source)) {
                $missing[] = "{$selector} has no light-mode rule; amber ink on a white page is how text disappears";
            }
        }

        $this->assertSame([], $missing, implode("\n", $missing));
    }

    /**
     * Descriptions are levelled by measurement, not cut to a fixed box.
     *
     * `line-clamp-3` held every description to three lines so the price panels
     * below them would line up. Two of the nine plans wrote longer copy, so
     * two cards in a row ended in an ellipsis mid-sentence -- "...getting
     * started with their first bio..." -- while the rest read as finished
     * sentences. It lined the cards up by damaging the copy, and it was what
     * "the pricing columns look odd" turned out to mean.
     *
     * The boxes are levelled to the tallest of them at runtime instead. That
     * holds for whatever an admin types next, which a fixed box never can.
     */
    public function test_descriptions_are_levelled_rather_than_clamped(): void
    {
        $source = $this->source();

        preg_match_all('/<p class="plan-band-desc[^"]*"/', $source, $descs);

        $this->assertCount(
            2,
            $descs[0],
            'expected a .plan-band-desc box on both the plan card and the coin card'
        );

        foreach ($descs[0] as $tag) {
            $this->assertStringNotContainsString(
                'line-clamp',
                $tag,
                'a card description is clamped again; the copy that overruns it gets cut mid-sentence'
            );
        }

        // The clamp is gone, so something has to keep the cards level.
        $this->assertStringContainsString(
            'levelDescriptions',
            $source,
            'the description equaliser is gone, so a longer description now pushes one card\'s price panel out of line'
        );
        $this->assertStringContainsString(
            "querySelectorAll('.plans-row, .coin-rail')",
            $source,
            'the equaliser no longer covers both rails'
        );
        // Measuring before the webfont lands measures the fallback face.
        $this->assertStringContainsString(
            'document.fonts.ready.then(levelDescriptions)',
            $source,
            'the equaliser no longer waits for fonts, so it levels against the fallback metrics'
        );
    }

    /**
     * Plan and package names are never truncated.
     *
     * "Professional" rendered as "Pro..." on the most-popular card -- the one
     * card the page most wants read -- because the name was `truncate`d to
     * leave room for the badge beside it, and at 290px there was no room to
     * leave: the name box measured 56px for 118px of word. The badge moved to
     * its own row. `truncate` coming back would undo that silently, and the
     * name it eats first is the flagship plan's.
     */
    public function test_the_card_names_are_not_truncated(): void
    {
        preg_match_all('/<h3 class="plan-band-name[^"]*"/', $this->source(), $names);

        $this->assertNotEmpty($names[0], 'no .plan-band-name headings found; this test is no longer reading the cards');

        foreach ($names[0] as $tag) {
            $this->assertStringNotContainsString(
                'truncate',
                $tag,
                'a card name is truncated again -- "Professional" becomes "Pro..." on the most-popular card'
            );
        }
    }
}
