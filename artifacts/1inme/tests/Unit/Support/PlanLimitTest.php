<?php

namespace Tests\Unit\Support;

use App\Support\PlanLimit;
use PHPUnit\Framework\TestCase;

class PlanLimitTest extends TestCase
{
    public function test_php_int_max_sentinel_is_unlimited(): void
    {
        $this->assertTrue(PlanLimit::isUnlimited(PHP_INT_MAX));
        $this->assertTrue(PlanLimit::isUnlimited((float) PHP_INT_MAX));
        $this->assertTrue(PlanLimit::isUnlimited(-1));
        $this->assertFalse(PlanLimit::isUnlimited(0));
        $this->assertFalse(PlanLimit::isUnlimited(25));
    }

    public function test_normalize_folds_unlimited_to_minus_one(): void
    {
        $this->assertSame(-1, PlanLimit::normalize(PHP_INT_MAX));
        $this->assertSame(-1, PlanLimit::normalize(-1));
        $this->assertSame(-1, PlanLimit::normalize(-5));
        $this->assertSame(0, PlanLimit::normalize(0));
        $this->assertSame(25, PlanLimit::normalize(25));
        $this->assertSame(3, PlanLimit::normalize(3.0));
    }
}
