<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The shared coin-cost badge's x-data expression is valid JavaScript.
 *
 * It was not. The partial opened aiCostEstimate( and then closed only the
 * @js([...]) sitting inside it:
 *
 *     x-data="aiCostEstimate(@js([ ... ])"
 *
 * so every page including the badge rendered an attribute one ")" short.
 * Alpine's evaluator threw "missing ) after argument list", the component's
 * scope came up empty, x-init="costInit()" ran against nothing, and the POST
 * to the estimate endpoint never fired once. x-cloak is stripped either way,
 * so the badge rendered as an empty box rather than as nothing at all, which
 * is how it survived on every page that uses it -- persona, minds, ask-coach,
 * the resume import modal, the contacts card scan and the competitor teardown
 * -- without the missing price being obvious.
 *
 * Nothing in the suite looked at the attribute. A Blade syntax error would
 * have been caught by BladeViewsCompileTest; a JavaScript syntax error inside
 * a Blade attribute is, to Blade, just a string. So this renders the partial
 * and reads the attribute back out.
 *
 * Both guards were checked against the attribute production was actually
 * serving: the shape assertion and the paren count each fail on that string
 * and pass once the ")" is added.
 */
class CostEstimateBadgeBootsTest extends TestCase
{
    private const SAMPLE = [
        'feature' => 'competitor_teardown',
        'mode'    => 'fixed',
        'prefix'  => 'Up to',
        'note'    => 'per teardown',
    ];

    private function renderedXData(array $cost): string
    {
        $html = view('user.ai._partials.cost-estimate', ['cost' => $cost])->render();

        $this->assertSame(
            1,
            preg_match('/\sx-data="([^"]*)"/', $html, $m),
            'the badge rendered without an x-data attribute at all'
        );

        return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    }

    public function test_the_x_data_expression_closes_the_call_it_opens(): void
    {
        $xData = $this->renderedXData(self::SAMPLE);

        $this->assertMatchesRegularExpression(
            "/^aiCostEstimate\(JSON\.parse\('.*'\)\)\z/s",
            $xData,
            'x-data must read aiCostEstimate(JSON.parse(\'...\')). Alpine evaluates it as '
            .'JavaScript, so one missing paren leaves the whole badge inert. Got: '.$xData
        );
    }

    public function test_the_parentheses_balance_outside_the_config_literal(): void
    {
        $xData = $this->renderedXData(self::SAMPLE);

        // Laravel's @js hex-escapes apostrophes (JSON_HEX_APOS), so the single
        // quoted literal cannot contain one and this strip is unambiguous.
        $outside = preg_replace("/'[^']*'/", "''", $xData);

        $this->assertSame(
            substr_count($outside, '('),
            substr_count($outside, ')'),
            'the x-data expression opens and closes a different number of parens: '.$outside
        );
    }

    /**
     * This one does NOT guard the missing paren -- checked against the real
     * attribute production was serving, it passes on the broken string as
     * readily as on the fixed one, because JSON.parse's argument was never
     * what was malformed. It guards the other half: that @js still hands the
     * component a config it can read. The two tests above are the regression.
     */
    public function test_the_config_the_badge_is_handed_survives_the_round_trip(): void
    {
        $xData = $this->renderedXData(self::SAMPLE);

        $this->assertSame(1, preg_match("/JSON\.parse\('(.*)'\)/s", $xData, $m));

        // Resolve the \uXXXX and \/ escapes the browser resolves before
        // JSON.parse ever sees the string.
        $json = preg_replace_callback(
            '/\\\\u([0-9a-fA-F]{4})/',
            static fn (array $hit): string => mb_chr(hexdec($hit[1]), 'UTF-8'),
            $m[1]
        );
        $json = str_replace('\\/', '/', $json);

        $cfg = json_decode($json, true);

        $this->assertIsArray($cfg, 'the config the badge is handed is not valid JSON: '.$json);
        $this->assertSame('competitor_teardown', $cfg['feature'] ?? null);
        $this->assertSame('fixed', $cfg['mode'] ?? null);
        $this->assertSame('Up to', $cfg['prefix'] ?? null);
        $this->assertSame('per teardown', $cfg['note'] ?? null);
        $this->assertNotEmpty($cfg['endpoint'] ?? null, 'without an endpoint costRefresh() returns before it fetches');
    }
}
