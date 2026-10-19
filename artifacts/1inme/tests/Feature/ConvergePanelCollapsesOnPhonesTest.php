<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The converge panel has to become one column on a phone.
 *
 * It did not. @media(max-width:760px) collapsed the bare .ch-panel (0,1,0),
 * while .ch-converge .ch-panel (0,2,0) re-declared two columns outside any
 * media query -- and specificity beats the media query, every time. What
 * shipped was a 115px copy column inside a 374px viewport: 15px type at about
 * fifteen characters a line, nine lines for one sentence.
 *
 * The obvious test here would assert the stylesheet CONTAINS a rule. That is
 * the test that does not work: the broken version contained a collapsing rule
 * too, it just lost the cascade. So this resolves the cascade the way a
 * browser would -- every rule that matches the element at phone width, highest
 * specificity first, last declaration wins -- and asserts the value that
 * actually applies.
 */
class ConvergePanelCollapsesOnPhonesTest extends TestCase
{
    private const PHONE_WIDTH = 430;

    /** Ancestors that may legitimately appear in front of .ch-panel here. */
    private const ANCESTORS = ['ch-converge', 'ch-demo', 'ch-section', 'ch-journey'];

    public function test_the_converge_panel_resolves_to_one_column_at_phone_width(): void
    {
        $winner = $this->winningGridColumns();

        $this->assertNotNull($winner, 'no rule sets grid-template-columns on .ch-converge .ch-panel at all');
        $this->assertSame(
            '1fr',
            $winner['value'],
            "at {$this->phone()}px the converge panel resolves to '{$winner['value']}' (from `{$winner['selector']}`"
                .($winner['media'] ? " inside @media{$winner['media']}" : ' with no media query')
                .'), so the copy column is squeezed beside the cards instead of stacking under them'
        );
    }

    private function phone(): int
    {
        return self::PHONE_WIDTH;
    }

    /**
     * @return array{selector: string, media: ?string, value: string}|null
     */
    private function winningGridColumns(): ?array
    {
        $candidates = [];

        foreach ($this->rules() as $order => $rule) {
            if (! $this->appliesAtPhoneWidth($rule['media'])) {
                continue;
            }
            foreach (explode(',', $rule['selector']) as $part) {
                $part = trim($part);
                if (! $this->matchesConvergePanel($part)) {
                    continue;
                }
                if (! preg_match('/(?:^|;)\s*grid-template-columns\s*:\s*([^;]+)/i', $rule['body'], $m)) {
                    continue;
                }
                $candidates[] = [
                    'selector'    => $part,
                    'media'       => $rule['media'],
                    'value'       => trim($m[1]),
                    'specificity' => substr_count($part, '.'),
                    'order'       => $order,
                ];
            }
        }

        if ($candidates === []) {
            return null;
        }

        // Highest specificity wins; ties go to whichever is declared last.
        usort($candidates, fn ($a, $b) => [$a['specificity'], $a['order']] <=> [$b['specificity'], $b['order']]);

        return end($candidates);
    }

    /** A selector that would match a .ch-panel sitting inside .ch-converge. */
    private function matchesConvergePanel(string $selector): bool
    {
        if (! str_ends_with($selector, '.ch-panel')) {
            return false;
        }
        preg_match_all('/\.([a-z0-9_-]+)/i', $selector, $m);
        foreach ($m[1] as $class) {
            if ($class !== 'ch-panel' && ! in_array($class, self::ANCESTORS, true)) {
                return false;
            }
        }

        return true;
    }

    private function appliesAtPhoneWidth(?string $media): bool
    {
        if ($media === null) {
            return true;
        }
        if (preg_match('/max-width\s*:\s*(\d+)px/i', $media, $m)) {
            return self::PHONE_WIDTH <= (int) $m[1];
        }
        if (preg_match('/min-width\s*:\s*(\d+)px/i', $media, $m)) {
            return self::PHONE_WIDTH >= (int) $m[1];
        }

        return false;
    }

    /**
     * Flatten the stylesheet into ordered [selector, media, body] rules.
     *
     * Comments are stripped FIRST. A regex that reads selectors out of CSS
     * happily reports the inside of a comment as a selector -- which is how a
     * guard I wrote earlier in this file's history scored its own explanatory
     * prose as a rule.
     *
     * @return list<array{selector: string, media: ?string, body: string}>
     */
    private function rules(): array
    {
        $css = file_get_contents(resource_path('views/home/partials/compact-home-styles.blade.php'));
        $css = preg_replace('#/\*[\s\S]*?\*/#', '', $css);
        $css = preg_replace('#</?style[^>]*>#i', '', $css);

        $rules = [];
        $media = null;
        $depth = 0;
        $buffer = '';
        $selector = null;

        foreach (str_split($css) as $char) {
            if ($char === '{') {
                $depth++;
                $head = trim($buffer);
                $buffer = '';
                if ($depth === 1 && str_starts_with($head, '@')) {
                    $media = str_starts_with(strtolower($head), '@media') ? trim(substr($head, 6)) : null;
                    $selector = null;
                } else {
                    $selector = $head;
                }
                continue;
            }

            if ($char === '}') {
                if ($depth >= 1 && $selector !== null) {
                    $rules[] = ['selector' => $selector, 'media' => $media, 'body' => $buffer];
                    $selector = null;
                }
                $depth--;
                if ($depth <= 0) {
                    $depth = 0;
                    $media = null;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        return $rules;
    }
}
