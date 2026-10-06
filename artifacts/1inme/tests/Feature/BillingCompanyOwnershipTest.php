<?php

namespace Tests\Feature;

use App\Modules\User\Controllers\TaxRuleController;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\TaxRule;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BillingCompanyOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_tax_rule_can_link_an_owned_company_but_rejects_a_foreign_company(): void
    {
        $owner = User::create(['name' => 'Company owner', 'email' => Str::random(12).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $other = User::create(['name' => 'Other owner', 'email' => Str::random(12).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $owned = BillingCompany::create(['user_id' => $owner->id, 'name' => 'Owned']);
        $foreign = BillingCompany::create(['user_id' => $other->id, 'name' => 'Foreign']);
        $this->actingAs($owner);
        $controller = app(TaxRuleController::class);
        $controller->store(Request::create('/', 'POST', ['name' => 'Owned tax', 'rate_bps' => 500, 'billing_company_id' => $owned->id, 'is_active' => true]));
        $this->assertDatabaseHas('tax_rules', ['user_id' => $owner->id, 'billing_company_id' => $owned->id, 'name' => 'Owned tax']);
        try {
            $controller->store(Request::create('/', 'POST', ['name' => 'Foreign tax', 'rate_bps' => 500, 'billing_company_id' => $foreign->id]));
            $this->fail('A foreign billing company must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('billing_company_id', $exception->errors());
        }
        $this->assertFalse(TaxRule::where('name', 'Foreign tax')->exists());
    }
    public function test_inline_company_tax_is_owned_split_and_selected_as_default(): void
    {
        $owner = User::create(['name' => 'Inline owner', 'email' => Str::random(12).'@example.com', 'password' => bcrypt('test'), 'status' => 'active']);
        $this->actingAs($owner);
        $company = BillingCompany::create(['user_id' => $owner->id, 'name' => 'Inline company']);
        $controller = app(\App\Modules\User\Controllers\BillingCompanyController::class);
        $validate = new \ReflectionMethod($controller, 'validateNewTax');
        $save = new \ReflectionMethod($controller, 'createNewTax');
        $request = Request::create('/', 'POST', ['new_tax' => [
            'enabled' => 1, 'name' => 'Split GST', 'rate_percent' => 0,
            'inclusive' => 1, 'make_default' => 1,
            'components' => [['name' => 'CGST', 'rate_percent' => 2.5], ['name' => 'SGST', 'rate_percent' => 2.5]],
        ]]);
        $data = $validate->invoke($controller, $request);
        $save->invoke($controller, $company, $data, $request);
        $rule = TaxRule::findOrFail($company->fresh()->default_tax_rule_id);
        $this->assertSame((int) $owner->id, (int) $rule->user_id);
        $this->assertSame((int) $company->id, (int) $rule->billing_company_id);
        $this->assertSame(500, (int) $rule->rate_bps);
        $this->assertTrue($rule->inclusive);
        $this->assertSame([250, 250], array_column($rule->components, 'rate_bps'));
        $this->assertNull($validate->invoke($controller, Request::create('/', 'POST', ['new_tax' => ['enabled' => 0]])));
    }

}
