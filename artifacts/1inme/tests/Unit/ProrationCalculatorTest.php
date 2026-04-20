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
        $r = ProrationCalculator::prorateMinor(
            999, 999, 'monthly',
            new \DateTimeImmutable('2026-04-01'),
            new \DateTimeImmutable('2026-04-30'),
        );
        $this->assertSame(0, $r['amount_minor']);
        $this->assertFalse($r['is_upgrade']);
    }

    public function test_downgrade_attempt_is_free(): void
    {
        $r = ProrationCalculator::prorateMinor(
            1999, 999, 'monthly',
            new \DateTimeImmutable('2026-04-01'),
            new \DateTimeImmutable('2026-04-30'),
        );
        $this->assertSame(0, $r['amount_minor']);
        $this->assertFalse($r['is_upgrade']);
    }

    public function test_last_day_of_cycle_charges_at_least_one_day(): void
    {
        // fromPrice=1000, toPrice=2000, now==period_end → days_left=1.
        // Spec: planB_price × days_left / days_in_cycle = 2000 × 1 / 30 = 66.
        $r = ProrationCalculator::prorateMinor(
            1000, 2000, 'monthly',
            new \DateTimeImmutable('2026-04-30'),
            new \DateTimeImmutable('2026-04-30'),
        );
        $this->assertTrue($r['is_upgrade']);
        $this->assertSame(1, $r['days_left']);
        $this->assertSame(intdiv(2000 * 1, 30), $r['amount_minor']);
    }

    public function test_midcycle_upgrade_charges_full_new_plan_prorated(): void
    {
        // 16 days remaining in a 30-day cycle. Spec formula uses the
        // FULL new-plan price, not the delta: 2000 × 16 / 30 = 1066.
        $now = new \DateTimeImmutable('2026-04-15');
        $end = new \DateTimeImmutable('2026-04-30');
        $r = ProrationCalculator::prorateMinor(1000, 2000, 'monthly', $now, $end);
        $this->assertTrue($r['is_upgrade']);
        $this->assertSame(16, $r['days_left']);
        $this->assertSame(intdiv(2000 * 16, 30), $r['amount_minor']);
    }

    public function test_annual_cycle_uses_365_day_divisor(): void
    {
        $now = new \DateTimeImmutable('2026-04-01');
        $end = new \DateTimeImmutable('2027-03-31'); // 365-day cycle
        $r = ProrationCalculator::prorateMinor(12000, 24000, 'annual', $now, $end);
        $this->assertSame(365, $r['days_in_cycle']);
        $this->assertTrue($r['is_upgrade']);
        // 24000 * 365 / 365 = 24000
        $this->assertSame(24000, $r['amount_minor']);
    }
}
