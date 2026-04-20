<?php

namespace Tests\Unit;

use App\Modules\Admin\Models\Plan;
use App\Services\Billing\ProrationCalculator;
use PHPUnit\Framework\TestCase;

class ProrationCalculatorTest extends TestCase
{
    protected function plan(int $id, float $monthly, float $annual): Plan
    {
        $p = new Plan();
        $p->id = $id;
        $p->monthly_price = $monthly;
        $p->annual_price  = $annual;
        return $p;
    }

    public function test_same_plan_is_free(): void
    {
        $plan = $this->plan(1, 9.99, 99.90);
        $r = ProrationCalculator::prorate(
            $plan, $plan, 'monthly',
            new \DateTimeImmutable('2026-04-01'),
            new \DateTimeImmutable('2026-04-30'),
        );
        $this->assertSame(0, $r['amount_minor']);
        $this->assertFalse($r['is_upgrade']);
    }

    public function test_downgrade_attempt_is_free(): void
    {
        $a = $this->plan(1, 19.99, 199.99);
        $b = $this->plan(2, 9.99, 99.99);
        $r = ProrationCalculator::prorate(
            $a, $b, 'monthly',
            new \DateTimeImmutable('2026-04-01'),
            new \DateTimeImmutable('2026-04-30'),
        );
        $this->assertSame(0, $r['amount_minor']);
        $this->assertFalse($r['is_upgrade']);
    }

    public function test_last_day_of_cycle_charges_at_least_one_day(): void
    {
        $a = $this->plan(1, 10.00, 100.00);
        $b = $this->plan(2, 20.00, 200.00);
        // now == period_end
        $r = ProrationCalculator::prorate(
            $a, $b, 'monthly',
            new \DateTimeImmutable('2026-04-30'),
            new \DateTimeImmutable('2026-04-30'),
        );
        $this->assertTrue($r['is_upgrade']);
        $this->assertSame(1, $r['days_left']);
        // delta=1000 minor; 1/30 day → 33 minor (floor)
        $this->assertSame((int) floor(1000 * 1 / 30), $r['amount_minor']);
    }

    public function test_midcycle_upgrade_charges_delta_prorated(): void
    {
        $a = $this->plan(1, 10.00, 100.00);
        $b = $this->plan(2, 20.00, 200.00);
        // 15 days remaining in a 30-day cycle
        $now = new \DateTimeImmutable('2026-04-15');
        $end = new \DateTimeImmutable('2026-04-30');
        $r = ProrationCalculator::prorate($a, $b, 'monthly', $now, $end);
        $this->assertTrue($r['is_upgrade']);
        // delta 1000 minor * 16/30 = 533 (since diff between 15th and 30th is 15 days + the 1)
        $this->assertSame(16, $r['days_left']);
        $this->assertSame(intdiv(1000 * 16, 30), $r['amount_minor']);
    }

    public function test_annual_cycle_uses_365_day_divisor(): void
    {
        $a = $this->plan(1, 10.00, 120.00);
        $b = $this->plan(2, 20.00, 240.00);
        $now = new \DateTimeImmutable('2026-04-01');
        $end = new \DateTimeImmutable('2027-03-31'); // 364 day diff -> days_left 365
        $r = ProrationCalculator::prorate($a, $b, 'annual', $now, $end);
        $this->assertSame(365, $r['days_in_cycle']);
        $this->assertTrue($r['is_upgrade']);
        $this->assertGreaterThan(0, $r['amount_minor']);
    }
}
