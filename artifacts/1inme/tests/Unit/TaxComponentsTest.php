<?php
namespace Tests\Unit;

use App\Modules\User\Models\TaxRule;
use App\Services\Billing\InvoiceCalculator;
use App\Services\Billing\TaxComponents;
use PHPUnit\Framework\TestCase;

class TaxComponentsTest extends TestCase
{
    public function test_split_gst_preserves_exclusive_inclusive_and_discount_totals(): void
    {
        $rule = new TaxRule(['name' => 'GST', 'rate_bps' => 500, 'inclusive' => false, 'components' => [['name' => 'CGST', 'rate_bps' => 250], ['name' => 'SGST', 'rate_bps' => 250]]]);
        $calculator = new InvoiceCalculator();
        $exclusive = $calculator->compute([['amount_minor' => 10000]], 0, $rule);
        $this->assertSame(10500, $exclusive['grand_total_minor']);
        $this->assertSame([250, 250], array_column($exclusive['tax_breakdown'], 'amount_minor'));
        $discounted = $calculator->compute([['amount_minor' => 10000]], 1000, $rule);
        $this->assertSame(9450, $discounted['grand_total_minor']);
        $this->assertSame([225, 225], array_column($discounted['tax_breakdown'], 'amount_minor'));
        $rule->inclusive = true;
        $inclusive = $calculator->compute([['amount_minor' => 10500]], 0, $rule);
        $this->assertSame(10000, $inclusive['subtotal_minor']);
        $this->assertSame(10500, $inclusive['grand_total_minor']);
        $this->assertSame([250, 250], array_column($inclusive['tax_breakdown'], 'amount_minor'));
    }

    public function test_rounding_conserves_pennies_and_never_assigns_tax_to_zero_rate(): void
    {
        $parts = TaxComponents::allocate(1, [['name' => 'A', 'rate_bps' => 100], ['name' => 'B', 'rate_bps' => 100], ['name' => 'C', 'rate_bps' => 100], ['name' => 'Zero', 'rate_bps' => 0]]);
        $this->assertSame(1, array_sum(array_column($parts, 'amount_minor')));
        $this->assertSame(0, $parts[3]['amount_minor']);
        $this->assertSame([], TaxComponents::allocate(0, []));
    }

    public function test_component_snapshot_survives_recalculation_without_the_original_rule(): void
    {
        $rule = new TaxRule(['name' => 'GST', 'rate_bps' => 500, 'components' => [['name' => 'CGST', 'rate_bps' => 250], ['name' => 'SGST', 'rate_bps' => 250]]]);
        $calculator = new InvoiceCalculator();
        $first = $calculator->compute([['amount_minor' => 10000]], 0, $rule);
        $again = $calculator->compute($first['line_items']);
        $this->assertSame($first['tax_breakdown'], $again['tax_breakdown']);
        $this->assertSame($first['grand_total_minor'], $again['grand_total_minor']);
    }
}
