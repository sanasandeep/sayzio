<?php

namespace Tests\Unit;

use App\Modules\User\Support\MenuBillingCompany;
use PHPUnit\Framework\TestCase;

class MenuBillingCompanyTaxTest extends TestCase
{
    public function test_store_tax_uses_linked_profile_and_preserves_untaxed_menus(): void
    {
        $menu = (object) ['currency' => 'INR', 'settings' => ['tax' => ['enabled' => true, 'rate' => 5]]];
        $this->assertSame(100.0, MenuBillingCompany::storeBill($menu, 100, 'takeaway')['total']);
        $menu->settings['billing_company_id'] = 1;
        $exclusive = MenuBillingCompany::storeBill($menu, 100, 'takeaway');
        $this->assertSame(5.0, $exclusive['tax_amount']);
        $this->assertSame(105.0, $exclusive['total']);
        $menu->settings['tax']['inclusive'] = true;
        $inclusive = MenuBillingCompany::storeBill($menu, 105, 'takeaway');
        $this->assertSame(5.0, $inclusive['tax_amount']);
        $this->assertSame(105.0, $inclusive['total']);
        $menu->settings['tax']['enabled'] = false;
        $this->assertSame(0.0, MenuBillingCompany::storeBill($menu, 105, 'takeaway')['tax_amount']);
    }
    public function test_store_split_tax_is_saved_as_components_without_changing_total(): void
    {
        $menu = (object) ['currency' => 'INR', 'settings' => ['billing_company_id' => 1, 'tax' => ['enabled' => true, 'rate' => 5, 'components' => [['name' => 'CGST', 'rate_bps' => 250], ['name' => 'SGST', 'rate_bps' => 250]]]]];
        $bill = MenuBillingCompany::storeBill($menu, 100, 'takeaway');
        $this->assertSame(105.0, $bill['total']);
        $this->assertSame([250, 250], array_column($bill['tax_breakdown'], 'amount_minor'));
    }

}
