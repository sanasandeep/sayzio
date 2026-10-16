<?php

namespace Tests\Feature;

use Database\Seeders\PlansAndAddonsSeeder;
use Tests\TestCase;

/**
 * The public plan ladder must never invert.
 *
 * This guard exists because it did. Bio Plus at $8 outranked Creator at $12 on
 * bio pages, short links, custom domains, badge removal and teams; Professional
 * at $25 still showed the Sayzio badge that $8 removed. Every plan was correct
 * on its own admin screen — the bug only existed BETWEEN plans, which is
 * exactly the shape no per-plan check can see.
 *
 * So this test compares each plan with the one above it, on every key in the
 * canonical matrix, and says which pair broke and how.
 */
class PlanLadderMonotonicityTest extends TestCase
{
    /** Ordered weakest to strongest. A tier not listed here fails loudly. */
    private const ANALYTICS_RANK = ['basic' => 0, 'advanced' => 1];

    /**
     * Keys that are allowed to stay flat all the way up because they gate a
     * product boundary rather than a quantity. Nothing is currently exempt —
     * the list exists so a future exemption has to be written down here, with
     * a reason, rather than quietly weakening the comparison.
     *
     * @var list<string>
     */
    private const EXEMPT = [];

    public function test_each_plan_beats_the_one_below_it_on_every_gated_feature(): void
    {
        $ladder = PlansAndAddonsSeeder::publicPlanLadder();
        $slugs  = array_keys($ladder);

        $this->assertGreaterThan(1, count($slugs), 'The ladder needs at least two plans to compare.');

        $breaks = [];

        for ($i = 1; $i < count($slugs); $i++) {
            $lowerSlug = $slugs[$i - 1];
            $upperSlug = $slugs[$i];
            $lower     = $ladder[$lowerSlug];
            $upper     = $ladder[$upperSlug];

            foreach ($lower as $key => $lowerValue) {
                if (in_array($key, self::EXEMPT, true)) {
                    continue;
                }

                $this->assertArrayHasKey($key, $upper, sprintf(
                    "Plan '%s' is missing '%s', which '%s' below it declares. Every plan must "
                    ."declare the whole matrix, or a gap silently falls back to a call-site default.",
                    $upperSlug,
                    $key,
                    $lowerSlug,
                ));

                $upperValue = $upper[$key];

                if (! $this->isAtLeast($key, $upperValue, $lowerValue)) {
                    $breaks[] = sprintf(
                        "%s -> %s : %s goes from %s down to %s",
                        $lowerSlug,
                        $upperSlug,
                        $key,
                        $this->show($lowerValue),
                        $this->show($upperValue),
                    );
                }
            }
        }

        $this->assertSame([], $breaks, sprintf(
            "The plan ladder is inverted. A dearer plan gives LESS than a cheaper one:\n\n  %s\n\n"
            ."Fix the values in PlansAndAddonsSeeder::publicPlanLadder(). If a reversal is genuinely\n"
            ."intended, add the key to self::EXEMPT with a comment saying why.\n",
            implode("\n  ", $breaks),
        ));
    }

    /** @param mixed $upper @param mixed $lower */
    private function isAtLeast(string $key, $upper, $lower): bool
    {
        // -1 means unlimited and therefore beats every finite cap.
        if (is_int($upper) && is_int($lower)) {
            if ($upper === -1) {
                return true;
            }
            if ($lower === -1) {
                return false;
            }

            return $upper >= $lower;
        }

        if (is_bool($upper) && is_bool($lower)) {
            return $upper || ! $lower;
        }

        if (is_string($upper) && is_string($lower)) {
            $this->assertArrayHasKey($upper, self::ANALYTICS_RANK, sprintf(
                "Unknown tier '%s' for '%s'. Add it to ANALYTICS_RANK in rank order so the "
                ."comparison stays meaningful.",
                $upper,
                $key,
            ));
            $this->assertArrayHasKey($lower, self::ANALYTICS_RANK, sprintf(
                "Unknown tier '%s' for '%s'. Add it to ANALYTICS_RANK in rank order.",
                $lower,
                $key,
            ));

            return self::ANALYTICS_RANK[$upper] >= self::ANALYTICS_RANK[$lower];
        }

        $this->fail(sprintf(
            "'%s' changes type between plans (%s vs %s). A gated feature has to keep one type "
            ."or the comparison is meaningless.",
            $key,
            get_debug_type($lower),
            get_debug_type($upper),
        ));
    }

    /** @param mixed $value */
    private function show($value): string
    {
        if (is_bool($value)) {
            return $value ? 'on' : 'off';
        }

        return $value === -1 ? 'unlimited' : (string) $value;
    }

    public function test_every_paid_plan_removes_the_sayzio_badge(): void
    {
        $ladder = PlansAndAddonsSeeder::publicPlanLadder();

        foreach ($ladder as $slug => $features) {
            if ($slug === 'free') {
                continue;
            }

            $this->assertTrue(
                (bool) ($features['remove_branding'] ?? false),
                "Paid plan '{$slug}' still carries the Sayzio badge. Charging for a page and "
                ."branding it is what made Professional at \$25 look worse than Bio Plus at \$8.",
            );
        }
    }
}
