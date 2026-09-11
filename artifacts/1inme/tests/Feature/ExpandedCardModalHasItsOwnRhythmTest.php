<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The expanded-card modal spaces its content for the size it renders it at.
 *
 * The modal clones a homepage card and enlarges it: a card's 18px heading
 * becomes up to 30px, its 13px copy becomes 15.5px. The spacing does not come
 * along -- it is Tailwind utilities baked into the card markup (`mb-4`,
 * `mb-1.5`, `mb-5`), sized for card-size type. Six pixels under an 18px
 * heading is a gap; six pixels under a 30px heading is not, and the modal
 * read as having no spacing at all.
 *
 * So this holds the pairing: where the modal scales a piece's type up, it
 * must also set that piece's spacing. A future edit that adds another
 * type-scaling rule without a matching margin is the regression here, and the
 * first test below is what notices.
 */
class ExpandedCardModalHasItsOwnRhythmTest extends TestCase
{
    private function homepageCss(): string
    {
        $response = $this->get('/');
        $response->assertOk();

        return $response->getContent();
    }

    /**
     * Every piece the modal re-sizes also gets its spacing re-set.
     *
     * Asserted as a pair rather than as two separate string checks, because
     * the failure mode is specifically one arriving without the other.
     */
    public function test_each_piece_the_modal_enlarges_also_gets_its_spacing_set(): void
    {
        $html = $this->homepageCss();

        $pairs = [
            // selector fragment => what it must also set
            '.xc-body :is(h3, h4)' => 'margin-bottom',
            '.xc-body .card-ico' => 'margin-bottom',
        ];

        foreach ($pairs as $selector => $property) {
            $this->assertStringContainsString(
                $selector,
                $html,
                "the modal no longer styles {$selector} at all"
            );

            // Pull every rule block for this selector and require one of them
            // to carry the spacing property.
            $blocks = [];
            $offset = 0;
            while (($at = strpos($html, $selector, $offset)) !== false) {
                $open = strpos($html, '{', $at);
                $close = strpos($html, '}', $at);
                if ($open === false || $close === false) {
                    break;
                }
                $blocks[] = substr($html, $open, $close - $open);
                $offset = $at + strlen($selector);
            }

            $carries = array_filter($blocks, fn ($b) => str_contains($b, $property));

            $this->assertNotEmpty(
                $carries,
                "{$selector} is re-sized in the modal but never given a {$property}. "
                . 'The card-sized margin it inherits collapses at modal type size, which is '
                . 'the "there is no spacing" bug.'
            );
        }
    }

    /**
     * The intro paragraph is separated from the controls under it.
     *
     * `mb-5` (20px) under a 15.5px paragraph, above a swatch row, is the gap
     * that made the top of the Themes modal look like one block of stuff.
     */
    public function test_the_intro_paragraph_clears_what_follows_it(): void
    {
        $this->assertStringContainsString(
            '.xc-body :is(h3, h4) + p',
            $this->homepageCss(),
            'the paragraph directly under a modal heading is back to its card margin'
        );
    }

    /**
     * A control strip cloned out of a card does not have its rows touching.
     *
     * `space-y-2` / `space-y-3` is 8-12px, which is right in a card and too
     * tight once the strip is 660px wide with larger labels.
     */
    public function test_a_cloned_control_strip_is_not_stacked_at_card_spacing(): void
    {
        $this->assertMatchesRegularExpression(
            '/\.xc-body :is\(\.space-y-2, \.space-y-3\)[^}]*margin-top/',
            $this->homepageCss(),
            'the swatch and font-pill rows are back to card spacing inside the modal'
        );
    }

    /**
     * The icon chip keeps its colour in the modal.
     *
     * The gradient is painted by `html .card-row .card-ico` off two custom
     * properties set on the ROW. The clone is not in that row, so it matched
     * nothing and rendered as a bare square with a dark glyph. open() copies
     * the computed paint across; without that copy there is no rule that can
     * reach it, so this asserts the copy itself.
     */
    public function test_the_cloned_icon_chip_carries_its_paint_across(): void
    {
        $html = $this->homepageCss();

        // Asserting the wiring, not a function name. The first version of
        // this test looked for "carryIconPaint", which survived renaming the
        // function to "carryIconPaintDISABLED_" -- a mutation that breaks
        // nothing at all here but showed the assertion was reading a label
        // rather than the behaviour.
        //
        // This is still a source-text assertion: PHPUnit cannot run the
        // clone and look at the pixels, so the live check for that was done
        // by hand in the browser. What it CAN hold is that the copy reads
        // from the source card and writes to the clone, which is the part a
        // later refactor would silently break.
        $this->assertMatchesRegularExpression(
            '/card\.querySelectorAll\(\s*[\'"]\.card-ico[\'"]\s*\)/',
            $html,
            'nothing reads the icon chip off the source card any more'
        );
        $this->assertMatchesRegularExpression(
            '/clone\.querySelectorAll\(\s*[\'"]\.card-ico[\'"]\s*\)/',
            $html,
            'nothing writes the icon paint onto the clone any more'
        );

        foreach (['backgroundImage', 'boxShadow'] as $property) {
            $this->assertStringContainsString(
                'style.' . $property,
                $html,
                "the icon-paint carry no longer copies {$property}, so the chip in the modal "
                . 'loses part of what the card paints it with'
            );
        }
    }

    /**
     * The cloned card's own chrome is flattened hard enough to actually win.
     *
     * `public/partials/surfaces` asserts card chrome on every `.glass` with
     * `!important` -- border, background and shadow. A plain inline style
     * loses to an important author declaration, so open()'s flattening was
     * being ignored and the modal drew a hairline box around its content,
     * with no padding inside it (correctly, since the modal supplies its
     * own 44px). Only an inline important declaration outranks an important
     * author one.
     *
     * Asserted on the property list rather than the exact call, so the
     * formatting can change; what must not change is that all four of these
     * are set with priority.
     */
    public function test_the_clone_flattens_its_card_chrome_with_enough_force_to_win(): void
    {
        $html = $this->homepageCss();

        $this->assertMatchesRegularExpression(
            '/setProperty\(\s*prop\s*,[^)]*,\s*[\'"]important[\'"]\s*\)/',
            $html,
            'the clone no longer flattens its card chrome with an important inline declaration, '
            . 'so the surfaces layer wins and the modal draws a box around its own content'
        );

        foreach (['background', 'border', 'box-shadow', 'padding'] as $property) {
            $this->assertStringContainsString(
                "'" . $property . "'",
                $html,
                "{$property} is no longer in the list of card chrome the clone flattens"
            );
        }
    }

    /**
     * And the card keeps its own spacing.
     *
     * Every rule above is scoped to .xc-body. If one ever lands unscoped it
     * re-spaces sixteen cards on the homepage grid, which is a much bigger
     * change than the one that was asked for.
     */
    public function test_none_of_this_leaks_out_of_the_modal(): void
    {
        $html = $this->homepageCss();

        // Find every rule that declares one of the modal's rhythm values and
        // read back the selector it is attached to. A substring check cannot
        // do this: ".xc-body .card-ico { margin-bottom: 26px" contains
        // ".card-ico { margin-bottom: 26px", so the scoped rule would look
        // like the unscoped one and this test would fail against correct code.
        //
        // Comments go first. The rules here are heavily commented, and a
        // comment block sits between the previous rule's `}` and this rule's
        // selector -- so walking back to the last `}` otherwise returns the
        // comment text as the selector.
        $html = preg_replace('#/\*.*?\*/#s', '', $html);
        foreach (['margin-bottom: 26px !important', 'margin-bottom: 14px !important'] as $declaration) {
            $offset = 0;
            $found = 0;

            while (($at = strpos($html, $declaration, $offset)) !== false) {
                $found++;
                $offset = $at + strlen($declaration);

                $braceAt = strrpos(substr($html, 0, $at), '{');
                $this->assertNotFalse($braceAt, 'malformed CSS around a modal rhythm rule');

                $prevClose = strrpos(substr($html, 0, $braceAt), '}');
                $selector = trim(substr(
                    $html,
                    $prevClose === false ? 0 : $prevClose + 1,
                    $braceAt - ($prevClose === false ? 0 : $prevClose + 1)
                ));

                $this->assertStringStartsWith(
                    '.xc-body',
                    $selector,
                    "a modal spacing rule ({$declaration}) is attached to \"{$selector}\", which is not "
                    . 'scoped to the modal -- it will re-space every card on the homepage grid'
                );
            }

            $this->assertGreaterThan(0, $found, "the modal rhythm rule {$declaration} is gone");
        }
    }
}
