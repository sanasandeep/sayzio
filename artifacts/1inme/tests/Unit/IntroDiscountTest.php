<?php

namespace Tests\Unit;

use App\Services\Billing\IntroDiscount;
use PHPUnit\Framework\TestCase;

/**
 * Pure-function coverage for the first-term intro discount math. No DB:
 * {@see IntroDiscount} is stateless config normalization + computation.
 */
class IntroDiscountTest extends TestCase
{
    // --- normalize() -------------------------------------------------

    public function test_normalize_returns_null_for_non_array(): void
    {
        $this->assertNull(IntroDiscount::normalize(null));
        $this->assertNull(IntroDiscount::normalize('nope'));
        $this->assertNull(IntroDiscount::normalize(42));
    }

    public function test_normalize_returns_null_when_disabled(): void
    {
        $this->assertNull(IntroDiscount::normalize([
            'enabled' => false,
            'type'    => 'percent',
            'percent' => 20,
        ]));
        // Missing `enabled` defaults to off.
        $this->assertNull(IntroDiscount::normalize([
            'type'    => 'percent',
            'percent' => 20,
        ]));
    }

    public function test_normalize_rejects_out_of_range_percent(): void
    {
        $this->assertNull(IntroDiscount::normalize([
            'enabled' => true, 'type' => 'percent', 'percent' => 0,
        ]));
        $this->assertNull(IntroDiscount::normalize([
            'enabled' => true, 'type' => 'percent', 'percent' => 101,
        ]));
        $this->assertNull(IntroDiscount::normalize([
            'enabled' => true, 'type' => 'percent', 'percent' => -5,
        ]));
    }

    public function test_normalize_defaults_to_all_cycles_when_none_picked(): void
    {
        $cfg = IntroDiscount::normalize([
            'enabled' => true, 'type' => 'percent', 'percent' => 25,
        ]);
        $this->assertNotNull($cfg);
        $this->assertSame(IntroDiscount::CYCLES, $cfg['cycles']);
        $this->assertSame('percent', $cfg['type']);
        $this->assertSame(25, $cfg['percent']);
        $this->assertNull($cfg['label']);
    }

    public function test_normalize_filters_unknown_cycles_and_dedupes(): void
    {
        $cfg = IntroDiscount::normalize([
            'enabled' => true,
            'type'    => 'percent',
            'percent' => 10,
            'cycles'  => ['monthly', 'monthly', 'weekly', 'annual'],
        ]);
        $this->assertSame(['monthly', 'annual'], $cfg['cycles']);
    }

    public function test_normalize_trims_and_caps_label(): void
    {
        $cfg = IntroDiscount::normalize([
            'enabled' => true, 'type' => 'percent', 'percent' => 10,
            'label'   => '  Launch offer  ',
        ]);
        $this->assertSame('Launch offer', $cfg['label']);

        $long = str_repeat('x', 200);
        $cfg = IntroDiscount::normalize([
            'enabled' => true, 'type' => 'percent', 'percent' => 10,
            'label'   => $long,
        ]);
        $this->assertSame(120, mb_strlen($cfg['label']));

        // Empty/whitespace label collapses to null.
        $cfg = IntroDiscount::normalize([
            'enabled' => true, 'type' => 'percent', 'percent' => 10,
            'label'   => '   ',
        ]);
        $this->assertNull($cfg['label']);
    }

    public function test_normalize_returns_null_for_fixed_with_no_positive_amount(): void
    {
        $this->assertNull(IntroDiscount::normalize([
            'enabled' => true, 'type' => 'fixed', 'fixed' => ['USD' => 0, 'INR' => 0],
        ]));
        // Negative amounts clamp to 0, so the sum is still <= 0.
        $this->assertNull(IntroDiscount::normalize([
            'enabled' => true, 'type' => 'fixed', 'fixed' => ['USD' => -500, 'INR' => -1],
        ]));
        // No fixed map at all.
        $this->assertNull(IntroDiscount::normalize([
            'enabled' => true, 'type' => 'fixed',
        ]));
    }

    public function test_normalize_fixed_clamps_negatives_and_fills_all_currencies(): void
    {
        $cfg = IntroDiscount::normalize([
            'enabled' => true,
            'type'    => 'fixed',
            'fixed'   => ['USD' => 500, 'INR' => -50],
        ]);
        $this->assertNotNull($cfg);
        $this->assertSame('fixed', $cfg['type']);
        $this->assertSame(['USD' => 500, 'INR' => 0], $cfg['fixed']);
        $this->assertArrayNotHasKey('percent', $cfg);
    }

    public function test_normalize_unknown_type_falls_back_to_percent(): void
    {
        $cfg = IntroDiscount::normalize([
            'enabled' => true, 'type' => 'bogus', 'percent' => 15,
        ]);
        $this->assertSame('percent', $cfg['type']);
        $this->assertSame(15, $cfg['percent']);
    }

    // --- compute() ---------------------------------------------------

    public function test_compute_returns_null_when_config_off(): void
    {
        $this->assertNull(IntroDiscount::compute(null, 'USD', 'monthly', 1000));
        $this->assertNull(IntroDiscount::compute(['enabled' => false], 'USD', 'monthly', 1000));
    }

    public function test_compute_percent_discount(): void
    {
        $d = IntroDiscount::compute(
            ['enabled' => true, 'type' => 'percent', 'percent' => 20, 'label' => 'Save 20%'],
            'USD', 'monthly', 1000
        );
        $this->assertNotNull($d);
        $this->assertSame(800, $d['first_minor']);
        $this->assertSame(1000, $d['normal_minor']);
        $this->assertSame(200, $d['amount_off_minor']);
        $this->assertSame(20, $d['percent_off']);
        $this->assertSame('percent', $d['type']);
        $this->assertSame('Save 20%', $d['label']);
    }

    public function test_compute_percent_rounds_to_nearest_minor_unit(): void
    {
        // 33% of 999 = 329.67 -> rounds to 330.
        $d = IntroDiscount::compute(
            ['enabled' => true, 'type' => 'percent', 'percent' => 33],
            'USD', 'monthly', 999
        );
        $this->assertSame(330, $d['amount_off_minor']);
        $this->assertSame(669, $d['first_minor']);
    }

    public function test_compute_fixed_is_per_currency(): void
    {
        $cfg = [
            'enabled' => true,
            'type'    => 'fixed',
            'fixed'   => ['USD' => 300, 'INR' => 5000],
        ];
        $usd = IntroDiscount::compute($cfg, 'USD', 'monthly', 1000);
        $this->assertSame(300, $usd['amount_off_minor']);
        $this->assertSame(700, $usd['first_minor']);

        $inr = IntroDiscount::compute($cfg, 'INR', 'monthly', 9900);
        $this->assertSame(5000, $inr['amount_off_minor']);
        $this->assertSame(4900, $inr['first_minor']);
    }

    public function test_compute_unknown_currency_falls_back_to_usd(): void
    {
        $cfg = ['enabled' => true, 'type' => 'fixed', 'fixed' => ['USD' => 250, 'INR' => 9000]];
        $d = IntroDiscount::compute($cfg, 'EUR', 'monthly', 1000);
        // EUR is not supported -> treated as USD, so the USD fixed amount applies.
        $this->assertSame(250, $d['amount_off_minor']);
    }

    public function test_compute_returns_null_when_cycle_excluded(): void
    {
        $cfg = [
            'enabled' => true, 'type' => 'percent', 'percent' => 50,
            'cycles'  => ['annual'],
        ];
        $this->assertNull(IntroDiscount::compute($cfg, 'USD', 'monthly', 1000));
        $this->assertNotNull(IntroDiscount::compute($cfg, 'USD', 'annual', 1000));
    }

    public function test_compute_returns_null_for_free_or_zero_price(): void
    {
        $cfg = ['enabled' => true, 'type' => 'percent', 'percent' => 50];
        $this->assertNull(IntroDiscount::compute($cfg, 'USD', 'monthly', 0));
        $this->assertNull(IntroDiscount::compute($cfg, 'USD', 'monthly', -100));
    }

    public function test_compute_returns_null_for_noop_fixed_reduction(): void
    {
        // Fixed discount set only for the OTHER currency -> 0 off here.
        $cfg = ['enabled' => true, 'type' => 'fixed', 'fixed' => ['USD' => 0, 'INR' => 5000]];
        $this->assertNull(IntroDiscount::compute($cfg, 'USD', 'monthly', 1000));
    }

    public function test_compute_caps_fixed_discount_at_normal_price(): void
    {
        // A fixed discount larger than the price clamps to the price: the
        // first term becomes free (0), never negative.
        $cfg = ['enabled' => true, 'type' => 'fixed', 'fixed' => ['USD' => 5000, 'INR' => 0]];
        $d = IntroDiscount::compute($cfg, 'USD', 'monthly', 1000);
        $this->assertNotNull($d);
        $this->assertSame(1000, $d['amount_off_minor']);
        $this->assertSame(0, $d['first_minor']);
        $this->assertSame(100, $d['percent_off']);
    }

    public function test_compute_percent_100_makes_first_term_free(): void
    {
        $cfg = ['enabled' => true, 'type' => 'percent', 'percent' => 100];
        $d = IntroDiscount::compute($cfg, 'USD', 'annual', 12000);
        $this->assertSame(0, $d['first_minor']);
        $this->assertSame(12000, $d['amount_off_minor']);
        $this->assertSame(100, $d['percent_off']);
    }
}
