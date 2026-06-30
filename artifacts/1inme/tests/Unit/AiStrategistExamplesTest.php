<?php

namespace Tests\Unit;

use App\Modules\Common\Support\AiStrategistExamples;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Task #3142 — the homepage "AI Marketing Strategist" card
 * (home.partials.ai-marketing-strategist) cycles through the example goals
 * returned by {@see AiStrategistExamples::all()} and reuses ONE fixed DOM:
 * exactly 3 organic rows + 2 paid rows, swapping only the inner icon/text in
 * place per example. If a new example arrives with the wrong number of plays
 * (or a missing key) the card silently renders a half-empty plan or repaints
 * stale rows — with no error.
 *
 * This guard pins that contract so a malformed example fails fast here instead
 * of shipping a broken card. It is a pure data check (the array is plain PHP,
 * no Laravel boot/DB needed), so it lives in the fast Unit suite.
 *
 * Mirrors the sibling fixed-shape demo source {@see \App\Modules\Common\Support\AiHeroExamples}.
 */
class AiStrategistExamplesTest extends TestCase
{
    private const ORGANIC_COUNT = 3;
    private const PAID_COUNT = 2;

    public function test_all_returns_a_non_empty_list(): void
    {
        $all = AiStrategistExamples::all();

        $this->assertIsArray($all);
        $this->assertNotEmpty($all, 'AiStrategistExamples::all() must return at least one example.');
        $this->assertSame(
            range(0, count($all) - 1),
            array_keys($all),
            'Examples must be a 0-indexed list (the JS cycle and resting markup index by position).',
        );
    }

    /**
     * @param array<string, mixed> $example
     */
    #[DataProvider('exampleProvider')]
    public function test_each_example_has_the_required_string_fields(int $index, array $example): void
    {
        foreach (['goal', 'head', 'kpi'] as $key) {
            $this->assertArrayHasKey($key, $example, "Example #{$index} is missing the '{$key}' field.");
            $this->assertIsString($example[$key], "Example #{$index} '{$key}' must be a string.");
            $this->assertNotSame('', trim($example[$key]), "Example #{$index} '{$key}' must not be blank.");
        }
    }

    /**
     * @param array<string, mixed> $example
     */
    #[DataProvider('exampleProvider')]
    public function test_each_example_has_exactly_three_organic_plays(int $index, array $example): void
    {
        $this->assertPlanGroup($example, 'organic', self::ORGANIC_COUNT, $index);
    }

    /**
     * @param array<string, mixed> $example
     */
    #[DataProvider('exampleProvider')]
    public function test_each_example_has_exactly_two_paid_plays(int $index, array $example): void
    {
        $this->assertPlanGroup($example, 'paid', self::PAID_COUNT, $index);
    }

    /**
     * Assert a plan group ('organic' or 'paid') has a non-blank tag and exactly
     * $expected items, each a {icon, text} pair with non-blank string values.
     *
     * @param array<string, mixed> $example
     */
    private function assertPlanGroup(array $example, string $group, int $expected, int $index): void
    {
        $this->assertArrayHasKey($group, $example, "Example #{$index} is missing the '{$group}' group.");
        $this->assertIsArray($example[$group], "Example #{$index} '{$group}' must be an array.");

        $this->assertArrayHasKey('tag', $example[$group], "Example #{$index} '{$group}' is missing its 'tag'.");
        $this->assertIsString($example[$group]['tag'], "Example #{$index} '{$group}.tag' must be a string.");
        $this->assertNotSame('', trim($example[$group]['tag']), "Example #{$index} '{$group}.tag' must not be blank.");

        $this->assertArrayHasKey('items', $example[$group], "Example #{$index} '{$group}' is missing its 'items'.");
        $this->assertIsArray($example[$group]['items'], "Example #{$index} '{$group}.items' must be an array.");
        $this->assertCount(
            $expected,
            $example[$group]['items'],
            "Example #{$index} '{$group}' must have exactly {$expected} items so the fixed card DOM stays in sync.",
        );
        // The JS renderer indexes rows by position (items[0], items[1], …), so
        // the items must be a 0-indexed sequential list — non-sequential keys
        // would JSON-encode as an object and silently drop rows.
        $this->assertSame(
            range(0, $expected - 1),
            array_keys($example[$group]['items']),
            "Example #{$index} '{$group}.items' must be a 0-indexed list so the JS fills rows by position.",
        );

        foreach (array_values($example[$group]['items']) as $i => $item) {
            $this->assertIsArray($item, "Example #{$index} '{$group}.items[{$i}]' must be an array.");
            foreach (['icon', 'text'] as $key) {
                $this->assertArrayHasKey($key, $item, "Example #{$index} '{$group}.items[{$i}]' is missing '{$key}'.");
                $this->assertIsString($item[$key], "Example #{$index} '{$group}.items[{$i}].{$key}' must be a string.");
                $this->assertNotSame('', trim($item[$key]), "Example #{$index} '{$group}.items[{$i}].{$key}' must not be blank.");
            }
        }
    }

    /**
     * @return iterable<string, array{0:int, 1:array<string, mixed>}>
     */
    public static function exampleProvider(): iterable
    {
        foreach (AiStrategistExamples::all() as $index => $example) {
            yield "example #{$index}" => [$index, $example];
        }
    }
}
