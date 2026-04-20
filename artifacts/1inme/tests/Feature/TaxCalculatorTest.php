<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\TaxJurisdiction;
use App\Services\InvoiceService;
use App\Services\TaxCalculator;
use Database\Seeders\TaxJurisdictionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the seven scenarios called out in task-192:
 *  1. IN intra-state (CGST + SGST)
 *  2. IN inter-state (IGST)
 *  3. IN with B2B GSTIN (still charges — input-tax-credit is buyer-side)
 *  4. EU B2C (charge buyer's country VAT)
 *  5. EU B2B with valid VATIN of buyer's country (reverse charge → 0%)
 *  6. US (no jurisdiction → 0%)
 *  7. Unknown country (→ 0%)
 *
 * Plus: invoice numbering reservation correctness.
 */
class TaxCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Merchant: Maharashtra, India. Used by all the IN scenarios.
        config()->set('billing.merchant.country', 'IN');
        config()->set('billing.merchant.gst_state', 'MH');
        config()->set('billing.merchant.gstin', '27AAACO9633K1ZK');
        $this->seed(TaxJurisdictionsSeeder::class);
    }

    /** Helper: a single line of 10000 minor units (₹100 / $100). */
    private function items(int $minor = 10000): array
    {
        return [['label' => 'Pro Plan', 'amount_minor' => $minor]];
    }

    public function test_in_intra_state_splits_into_cgst_and_sgst(): void
    {
        $r = TaxCalculator::calculate($this->items(), [
            'country' => 'IN', 'region' => 'MH', 'tax_id' => null, 'tax_id_kind' => null,
        ], 'INR');
        $this->assertSame(10000, $r['subtotal_minor']);
        $this->assertCount(2, $r['tax_breakdown']);
        $this->assertSame('CGST 9%', $r['tax_breakdown'][0]['label']);
        $this->assertSame('SGST 9%', $r['tax_breakdown'][1]['label']);
        $this->assertSame(900, $r['tax_breakdown'][0]['amount_minor']);
        $this->assertSame(900, $r['tax_breakdown'][1]['amount_minor']);
        $this->assertSame(11800, $r['grand_total_minor']);
        $this->assertSame('IN-MH', $r['place_of_supply']);
    }

    public function test_in_inter_state_uses_igst(): void
    {
        $r = TaxCalculator::calculate($this->items(), [
            'country' => 'IN', 'region' => 'KA', 'tax_id' => null, 'tax_id_kind' => null,
        ], 'INR');
        $this->assertCount(1, $r['tax_breakdown']);
        $this->assertSame('IGST 18%', $r['tax_breakdown'][0]['label']);
        $this->assertSame(1800, $r['tax_breakdown'][0]['amount_minor']);
        $this->assertSame(11800, $r['grand_total_minor']);
        $this->assertSame('IN-KA', $r['place_of_supply']);
    }

    public function test_in_b2b_gstin_still_charges_tax(): void
    {
        // GSTIN is recorded but does NOT zero the tax — that's reverse-charge for EU only.
        $r = TaxCalculator::calculate($this->items(), [
            'country' => 'IN', 'region' => 'KA',
            'tax_id' => '29AAAAA0000A1Z5', 'tax_id_kind' => 'GSTIN',
        ], 'INR');
        $this->assertSame(1800, $r['tax_total_minor']);
    }

    public function test_eu_b2c_charges_destination_country_vat(): void
    {
        // Merchant is in IN, so any EU sale is "merchant outside EU"
        // path. Without a VATIN it falls through to charging German VAT.
        $r = TaxCalculator::calculate($this->items(), [
            'country' => 'DE', 'region' => null, 'tax_id' => null, 'tax_id_kind' => null,
        ], 'EUR');
        $this->assertCount(1, $r['tax_breakdown']);
        $this->assertSame('VAT 19%', $r['tax_breakdown'][0]['label']);
        $this->assertSame(1900, $r['tax_breakdown'][0]['amount_minor']);
    }

    public function test_eu_b2b_with_valid_vatin_uses_reverse_charge(): void
    {
        // Merchant in IN, buyer in DE with a valid German VATIN → reverse charge.
        $r = TaxCalculator::calculate($this->items(), [
            'country' => 'DE', 'region' => null,
            'tax_id' => 'DE123456789', 'tax_id_kind' => 'VATIN',
        ], 'EUR');
        $this->assertSame(0, $r['tax_total_minor']);
        $this->assertSame(10000, $r['grand_total_minor']);
        $this->assertSame('Reverse charge — customer to account for VAT.', $r['reverse_charge_note']);
    }

    public function test_us_returns_zero_tax(): void
    {
        $r = TaxCalculator::calculate($this->items(), [
            'country' => 'US', 'region' => 'CA', 'tax_id' => null, 'tax_id_kind' => null,
        ], 'USD');
        $this->assertSame(0, $r['tax_total_minor']);
        $this->assertSame(10000, $r['grand_total_minor']);
        $this->assertSame('US', $r['place_of_supply']);
    }

    public function test_unknown_country_returns_zero_tax(): void
    {
        $r = TaxCalculator::calculate($this->items(), [
            'country' => '', 'region' => null, 'tax_id' => null, 'tax_id_kind' => null,
        ], 'USD');
        $this->assertSame(0, $r['tax_total_minor']);
        $this->assertNull($r['place_of_supply']);
    }

    public function test_invoice_numbering_is_sequential_per_fy(): void
    {
        $user = \App\Modules\User\Models\User::create([
            'name' => 'Tester', 'email' => 'tester+'.uniqid().'@example.com',
            'password' => 'x', 'status' => 'active', 'role' => 'user',
        ]);
        $calc = TaxCalculator::calculate($this->items(), [
            'country' => 'IN', 'region' => 'MH', 'tax_id' => null, 'tax_id_kind' => null,
        ], 'INR');
        $a = InvoiceService::issue($user, $calc, ['country' => 'IN', 'region' => 'MH']);
        $b = InvoiceService::issue($user, $calc, ['country' => 'IN', 'region' => 'MH']);
        $this->assertSame($a->financial_year, $b->financial_year);
        $this->assertSame($a->seq + 1, $b->seq);
        $this->assertNotSame($a->number, $b->number);
        $this->assertStringStartsWith('INV/' . $a->financial_year . '/', $a->number);
    }

    public function test_gstin_validator_recognises_a_valid_id(): void
    {
        // Compute a real checksum so the test isn't fragile to fixture choice.
        $first14 = '27AAACO9633K1Z';
        $check = \App\Modules\Admin\Rules\Gstin::checksum($first14);
        $this->assertTrue(\App\Modules\Admin\Rules\Gstin::isValid($first14 . $check));
    }

    public function test_gstin_validator_rejects_bad_checksum(): void
    {
        $this->assertFalse(\App\Modules\Admin\Rules\Gstin::isValid('27AAACO9633K1ZA'));
    }

    public function test_vatin_validator_per_country_prefix(): void
    {
        $this->assertTrue(\App\Modules\Admin\Rules\Vatin::isValid('DE123456789'));
        $this->assertTrue(\App\Modules\Admin\Rules\Vatin::isValid('GB123456789'));
        $this->assertFalse(\App\Modules\Admin\Rules\Vatin::isValid('XX123'));
        $this->assertFalse(\App\Modules\Admin\Rules\Vatin::isValid('DE12'));
    }

    public function test_seeded_jurisdictions_count(): void
    {
        $this->assertGreaterThan(60, TaxJurisdiction::count());
    }

    public function test_future_dated_rate_is_ignored_until_start_date(): void
    {
        // Park a future-dated row alongside the seeded one; today's calc
        // should still use the seeded 19% German VAT, not the future 25%.
        TaxJurisdiction::create([
            'country' => 'DE', 'region' => null, 'kind' => 'VAT',
            'label' => 'VAT 25%', 'rate_percent' => 25,
            'b2b_reverse_charge' => true, 'is_active' => true,
            'effective_from' => now()->addYear()->toDateString(),
            'effective_to' => null,
        ]);
        $r = TaxCalculator::calculate($this->items(), [
            'country' => 'DE', 'region' => null, 'tax_id' => null, 'tax_id_kind' => null,
        ], 'EUR');
        $this->assertSame(1900, $r['tax_breakdown'][0]['amount_minor']);
    }

    public function test_expired_rate_is_excluded(): void
    {
        // Mark the seeded German row as expired; calc should fall through to 0%.
        TaxJurisdiction::where('country', 'DE')->where('kind', 'VAT')->whereNull('region')
            ->update(['effective_to' => now()->subDay()->toDateString()]);
        $r = TaxCalculator::calculate($this->items(), [
            'country' => 'DE', 'region' => null, 'tax_id' => null, 'tax_id_kind' => null,
        ], 'EUR');
        $this->assertSame(0, $r['tax_total_minor']);
    }

    public function test_overlapping_rows_pick_most_recent_effective_from(): void
    {
        // A newer rate scheduled to start today should override the older row.
        TaxJurisdiction::create([
            'country' => 'DE', 'region' => null, 'kind' => 'VAT',
            'label' => 'VAT 21%', 'rate_percent' => 21,
            'b2b_reverse_charge' => true, 'is_active' => true,
            'effective_from' => now()->toDateString(),
            'effective_to' => null,
        ]);
        $rows = TaxJurisdiction::where('country','DE')->where('kind','VAT')->whereNull('region')->get();
        $debug = $rows->map(fn($r) => $r->id.':'.$r->label.':from='.($r->getRawOriginal('effective_from') ?: 'NULL'))->all();
        $r = TaxCalculator::calculate($this->items(), [
            'country' => 'DE', 'region' => null, 'tax_id' => null, 'tax_id_kind' => null,
        ], 'EUR');
        $this->assertSame('VAT 21%', $r['tax_breakdown'][0]['label'], 'rows: '.json_encode($debug));
    }

    public function test_reverse_charge_requires_explicit_vatin_kind(): void
    {
        // Same VATIN string, but kind=NONE → must NOT trigger reverse charge.
        $r = TaxCalculator::calculate($this->items(), [
            'country' => 'DE', 'region' => null,
            'tax_id' => 'DE123456789', 'tax_id_kind' => 'NONE',
        ], 'EUR');
        $this->assertSame(1900, $r['tax_total_minor']);
        $this->assertNull($r['reverse_charge_note']);
    }
}
