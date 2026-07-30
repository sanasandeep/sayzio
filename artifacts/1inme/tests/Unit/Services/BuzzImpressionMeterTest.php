<?php

namespace Tests\Unit\Services;

use App\Modules\User\Models\User;
use App\Services\BuzzImpressionMeter;
use PHPUnit\Framework\TestCase;

/**
 * Unit coverage for the unlimited-allowance handling in
 * BuzzImpressionMeter, specifically the plan-limit-bypass case where
 * User::getPlanFeature() returns PHP_INT_MAX instead of the canonical
 * -1 unlimited sentinel.
 */
class BuzzImpressionMeterTest extends TestCase
{
    private function userWithAllowance($allowance): User
    {
        $user = new class extends User {
            public $fakeAllowance = -1;

            public function getPlanFeature(string $key, $default = null)
            {
                return $this->fakeAllowance;
            }
        };
        $user->fakeAllowance = $allowance;

        return $user;
    }

    public function test_is_unlimited_thresholds(): void
    {
        $this->assertTrue(BuzzImpressionMeter::isUnlimited(-1));
        $this->assertTrue(BuzzImpressionMeter::isUnlimited(PHP_INT_MAX));
        $this->assertFalse(BuzzImpressionMeter::isUnlimited(0));
        $this->assertFalse(BuzzImpressionMeter::isUnlimited(50000));
    }

    public function test_allowance_for_normalises_bypass_max_int_to_minus_one(): void
    {
        $this->assertSame(-1, BuzzImpressionMeter::allowanceFor($this->userWithAllowance(PHP_INT_MAX)));
        $this->assertSame(-1, BuzzImpressionMeter::allowanceFor($this->userWithAllowance(-1)));
        $this->assertSame(50000, BuzzImpressionMeter::allowanceFor($this->userWithAllowance(50000)));
        $this->assertSame(-1, BuzzImpressionMeter::allowanceFor(null));
    }

    public function test_usage_summary_treats_bypass_allowance_as_unlimited(): void
    {
        $summary = BuzzImpressionMeter::usageSummary($this->userWithAllowance(PHP_INT_MAX));

        $this->assertTrue($summary['unlimited']);
        $this->assertSame(-1, $summary['allowance']);
        $this->assertNull($summary['remaining']);
        $this->assertSame(0, $summary['percent_used']);
        $this->assertFalse($summary['paused']);
    }

    public function test_usage_summary_finite_allowance_unchanged(): void
    {
        $summary = BuzzImpressionMeter::usageSummary($this->userWithAllowance(50000));

        $this->assertFalse($summary['unlimited']);
        $this->assertSame(50000, $summary['allowance']);
        $this->assertSame(50000, $summary['remaining']);
        $this->assertSame(0, $summary['percent_used']);
    }

    public function test_serving_never_paused_for_bypass_allowance(): void
    {
        $this->assertFalse(BuzzImpressionMeter::servingPaused($this->userWithAllowance(PHP_INT_MAX)));
    }
}
