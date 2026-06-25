<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression coverage for per-field (variable) form pricing — the math in
 * Form::computeAmountCents / Form::priceLineItems and the controller surfaces
 * that persist and gate it. A wrong total here charges buyers the wrong
 * amount, so every priced field type is exercised. See Task #2321 / #2339.
 */
class VariableFormPricingTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = []): Plan
    {
        $slug = 'p' . Str::random(6);
        return Plan::create([
            'name' => $slug, 'slug' => $slug,
            'monthly_price' => 0, 'annual_price' => 0,
            'trial_days' => 0, 'status' => 'active',
            'features' => $features,
        ]);
    }

    private function user(?Plan $plan = null): User
    {
        $u = User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'plan_id'  => $plan?->id,
        ]);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        return $u;
    }

    /**
     * Build a per_field form with the given fields + an optional base fee.
     * Not persisted unless $owner is provided — the compute helpers are pure
     * and don't touch the DB.
     */
    private function perFieldForm(array $fields, int $baseCents = 0, ?User $owner = null): Form
    {
        $payment = [
            'enabled'      => true,
            'mode'         => 'per_field',
            'amount_cents' => $baseCents,
            'currency'     => 'USD',
            'label'        => 'Order',
        ];
        if ($owner) {
            return $owner->forms()->create([
                'title'    => 'Priced Form',
                'fields'   => $fields,
                'settings' => array_merge(Form::defaultSettings(), ['payment' => $payment]),
                'is_active'=> true,
            ]);
        }
        $form = new Form();
        $form->fields = $fields;
        $form->settings = ['payment' => $payment];
        return $form;
    }

    /* -------------------- compute / line-item math -------------------- */

    public function test_fixed_mode_returns_flat_price_and_single_line_item(): void
    {
        $form = new Form();
        $form->settings = ['payment' => [
            'enabled' => true, 'mode' => 'fixed', 'amount_cents' => 2500,
            'currency' => 'USD', 'label' => 'Ticket',
        ]];

        // Fixed mode ignores submitted data entirely.
        $this->assertSame(2500, $form->computeAmountCents(['anything' => 'ignored']));

        $items = $form->priceLineItems([]);
        $this->assertCount(1, $items);
        $this->assertSame('__base__', $items[0]['field']);
        $this->assertSame('Ticket', $items[0]['label']);
        $this->assertSame(2500, $items[0]['amount_cents']);
    }

    public function test_fixed_mode_zero_price_yields_no_line_items(): void
    {
        $form = new Form();
        $form->settings = ['payment' => [
            'enabled' => true, 'mode' => 'fixed', 'amount_cents' => 0, 'currency' => 'USD',
        ]];
        $this->assertSame(0, $form->computeAmountCents([]));
        $this->assertSame([], $form->priceLineItems([]));
    }

    public function test_per_field_number_charges_unit_times_quantity(): void
    {
        $form = $this->perFieldForm([
            ['id' => 'qty', 'type' => 'number', 'label' => 'Tickets', 'price_cents' => 500],
        ]);

        $this->assertSame(1500, $form->computeAmountCents(['qty' => 3]));

        $items = $form->priceLineItems(['qty' => 3]);
        $this->assertCount(1, $items);
        $this->assertSame('qty', $items[0]['field']);
        $this->assertSame(1500, $items[0]['amount_cents']);
        $this->assertSame('3 × 5.00', $items[0]['detail']);
    }

    public function test_per_field_number_zero_or_missing_quantity_is_free(): void
    {
        $form = $this->perFieldForm([
            ['id' => 'qty', 'type' => 'number', 'label' => 'Tickets', 'price_cents' => 500],
        ]);
        $this->assertSame(0, $form->computeAmountCents(['qty' => 0]));
        $this->assertSame(0, $form->computeAmountCents([]));
        $this->assertSame([], $form->priceLineItems(['qty' => 0]));
    }

    public function test_per_field_select_charges_chosen_option_price(): void
    {
        $form = $this->perFieldForm([
            ['id' => 'size', 'type' => 'select', 'label' => 'Size',
             'options' => ['Small', 'Large'],
             'option_prices' => ['Small' => 300, 'Large' => 900]],
        ]);

        $this->assertSame(900, $form->computeAmountCents(['size' => 'Large']));
        $this->assertSame(300, $form->computeAmountCents(['size' => 'Small']));

        $items = $form->priceLineItems(['size' => 'Large']);
        $this->assertCount(1, $items);
        $this->assertSame('Large', $items[0]['detail']);
        $this->assertSame(900, $items[0]['amount_cents']);
    }

    public function test_per_field_select_unknown_or_unpriced_option_is_free(): void
    {
        $form = $this->perFieldForm([
            ['id' => 'size', 'type' => 'select', 'label' => 'Size',
             'option_prices' => ['Large' => 900]],
        ]);
        // Option without a price entry contributes nothing.
        $this->assertSame(0, $form->computeAmountCents(['size' => 'Small']));
        $this->assertSame(0, $form->computeAmountCents(['size' => '']));
    }

    public function test_per_field_radio_charges_chosen_option_price(): void
    {
        $form = $this->perFieldForm([
            ['id' => 'tier', 'type' => 'radio', 'label' => 'Tier',
             'option_prices' => ['Basic' => 0, 'Pro' => 1200]],
        ]);
        $this->assertSame(1200, $form->computeAmountCents(['tier' => 'Pro']));
        $this->assertSame(0, $form->computeAmountCents(['tier' => 'Basic']));
    }

    public function test_per_field_checkbox_sums_selected_option_prices(): void
    {
        $form = $this->perFieldForm([
            ['id' => 'addons', 'type' => 'checkbox', 'label' => 'Add-ons',
             'option_prices' => ['Gift wrap' => 200, 'Priority' => 700, 'Engraving' => 1500]],
        ]);

        $this->assertSame(900, $form->computeAmountCents(['addons' => ['Gift wrap', 'Priority']]));

        $items = $form->priceLineItems(['addons' => ['Gift wrap', 'Priority']]);
        $this->assertCount(2, $items);
        $this->assertSame(['Gift wrap', 'Priority'], array_column($items, 'detail'));
        $this->assertSame([200, 700], array_column($items, 'amount_cents'));

        // Unselected and unpriced selections contribute nothing.
        $this->assertSame(0, $form->computeAmountCents(['addons' => []]));
    }

    public function test_per_field_consent_is_a_flat_addon_only_when_accepted(): void
    {
        $form = $this->perFieldForm([
            ['id' => 'rush', 'type' => 'consent', 'label' => 'Rush handling', 'price_cents' => 450],
        ]);

        $this->assertSame(450, $form->computeAmountCents(['rush' => true]));
        $this->assertSame(450, $form->computeAmountCents(['rush' => '1']));
        $this->assertSame(0, $form->computeAmountCents(['rush' => false]));
        $this->assertSame(0, $form->computeAmountCents(['rush' => '0']));
        $this->assertSame([], $form->priceLineItems(['rush' => false]));
    }

    public function test_per_field_base_fee_adds_on_top_of_priced_fields(): void
    {
        $form = $this->perFieldForm([
            ['id' => 'qty', 'type' => 'number', 'label' => 'Tickets', 'price_cents' => 500],
            ['id' => 'size', 'type' => 'select', 'label' => 'Size',
             'option_prices' => ['Large' => 900]],
        ], baseCents: 1000);

        // base 1000 + (2 × 500) + 900 = 2900
        $this->assertSame(2900, $form->computeAmountCents(['qty' => 2, 'size' => 'Large']));

        $items = $form->priceLineItems(['qty' => 2, 'size' => 'Large']);
        $this->assertSame('__base__', $items[0]['field']);
        $this->assertSame(1000, $items[0]['amount_cents']);
        // Base row uses the configured payment label when one is set.
        $this->assertSame('Order', $items[0]['label']);
        // Base counted exactly once (regression: double-count guard).
        $this->assertSame(2900, array_sum(array_column($items, 'amount_cents')));
    }

    public function test_per_field_combination_across_all_priced_types(): void
    {
        $form = $this->perFieldForm([
            ['id' => 'qty', 'type' => 'number', 'label' => 'Qty', 'price_cents' => 250],
            ['id' => 'size', 'type' => 'select', 'label' => 'Size', 'option_prices' => ['L' => 800]],
            ['id' => 'tier', 'type' => 'radio', 'label' => 'Tier', 'option_prices' => ['Pro' => 1200]],
            ['id' => 'addons', 'type' => 'checkbox', 'label' => 'Add', 'option_prices' => ['Wrap' => 200, 'Card' => 100]],
            ['id' => 'rush', 'type' => 'consent', 'label' => 'Rush', 'price_cents' => 450],
            // Non-priced types are ignored even if present in data.
            ['id' => 'note', 'type' => 'textarea', 'label' => 'Note'],
        ], baseCents: 500);

        $total = $form->computeAmountCents([
            'qty' => 4, 'size' => 'L', 'tier' => 'Pro',
            'addons' => ['Wrap', 'Card'], 'rush' => true, 'note' => 'hello',
        ]);
        // 500 base + (4×250) + 800 + 1200 + (200+100) + 450 = 4250
        $this->assertSame(4250, $total);
    }

    public function test_per_field_with_no_selections_and_no_base_fee_is_zero(): void
    {
        $form = $this->perFieldForm([
            ['id' => 'qty', 'type' => 'number', 'label' => 'Qty', 'price_cents' => 500],
        ]);
        $this->assertSame(0, $form->computeAmountCents([]));
        $this->assertSame([], $form->priceLineItems([]));
    }

    /* ------------------------- publicSubmit -------------------------- */

    public function test_public_submit_stores_amount_and_line_items_for_per_field_form(): void
    {
        $owner = $this->user($this->plan(['paid_forms' => true]));
        CreatorPaymentConnection::create([
            'user_id' => $owner->id, 'provider' => 'stripe', 'account_id' => 'acct_test',
            'status' => 'active', 'payouts_enabled' => true, 'charges_enabled' => true,
            'is_default' => true,
        ]);

        $form = $this->perFieldForm([
            ['id' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
            ['id' => 'qty', 'type' => 'number', 'label' => 'Tickets', 'price_cents' => 500],
            ['id' => 'addons', 'type' => 'checkbox', 'label' => 'Add-ons',
             'option_prices' => ['Wrap' => 200]],
        ], baseCents: 1000, owner: $owner);

        // Public submit runs with no bound workspace — clear it.
        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $resp = $this->post(route('forms.public.submit', $form->slug), [
            'name'   => 'Jane',
            'qty'    => 3,
            'addons' => ['Wrap'],
        ]);

        // Paid submission redirects the visitor to the (preview) checkout.
        $resp->assertRedirect();

        $sub = $form->submissions()->withoutGlobalScopes()->firstOrFail();
        // base 1000 + (3×500) + 200 = 2700
        $this->assertSame(2700, $sub->amount_cents);
        $this->assertSame('pending', $sub->payment_status);

        $byField = collect($sub->line_items)->keyBy('field');
        $this->assertSame(1000, $byField['__base__']['amount_cents']);
        $this->assertSame(1500, $byField['qty']['amount_cents']);
        $this->assertSame(200, $byField['addons']['amount_cents']);
    }

    public function test_public_submit_with_no_priced_selection_is_free_and_not_pending(): void
    {
        $owner = $this->user($this->plan(['paid_forms' => true]));
        CreatorPaymentConnection::create([
            'user_id' => $owner->id, 'provider' => 'stripe', 'account_id' => 'acct_test',
            'status' => 'active', 'payouts_enabled' => true, 'charges_enabled' => true,
            'is_default' => true,
        ]);

        // No base fee; visitor picks nothing priced → total 0 → free submission.
        $form = $this->perFieldForm([
            ['id' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
            ['id' => 'qty', 'type' => 'number', 'label' => 'Tickets', 'price_cents' => 500],
        ], owner: $owner);

        app()->forgetInstance('current_workspace');
        app()->forgetInstance('workspace_owner');

        $this->post(route('forms.public.submit', $form->slug), [
            'name' => 'Jane',
            'qty'  => 0,
        ]);

        $sub = $form->submissions()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame(0, (int) $sub->amount_cents);
        $this->assertSame('none', $sub->payment_status);
        $this->assertNull($sub->line_items);
    }

    /* ------------------------- updatePayment ------------------------- */

    public function test_update_payment_blocks_per_field_enable_without_priced_fields_or_base_fee(): void
    {
        $owner = $this->user($this->plan(['paid_forms' => true]));
        CreatorPaymentConnection::create([
            'user_id' => $owner->id, 'provider' => 'stripe', 'account_id' => 'acct_test',
            'status' => 'active', 'payouts_enabled' => true, 'charges_enabled' => true,
            'is_default' => true,
        ]);

        // Form has no priced fields at all.
        $form = $owner->forms()->create([
            'title'  => 'Plain Form',
            'fields' => [['id' => 'name', 'type' => 'text', 'label' => 'Name']],
        ]);

        $resp = $this->actingAs($owner)->put(route('user.forms.payment.update', $form), [
            'enabled'  => '1',
            'mode'     => 'per_field',
            'amount'   => '0',
            'currency' => 'USD',
        ]);

        $resp->assertSessionHas('error');
        $form->refresh();
        $this->assertFalse((bool) ($form->paymentConfig()['enabled'] ?? false));
    }

    public function test_update_payment_allows_per_field_enable_with_base_fee(): void
    {
        $owner = $this->user($this->plan(['paid_forms' => true]));
        CreatorPaymentConnection::create([
            'user_id' => $owner->id, 'provider' => 'stripe', 'account_id' => 'acct_test',
            'status' => 'active', 'payouts_enabled' => true, 'charges_enabled' => true,
            'is_default' => true,
        ]);

        $form = $owner->forms()->create([
            'title'  => 'Plain Form',
            'fields' => [['id' => 'name', 'type' => 'text', 'label' => 'Name']],
        ]);

        $resp = $this->actingAs($owner)->put(route('user.forms.payment.update', $form), [
            'enabled'  => '1',
            'mode'     => 'per_field',
            'amount'   => '10',      // $10 base fee is a valid pricing source
            'currency' => 'USD',
        ]);

        $resp->assertSessionHas('success');
        $form->refresh();
        $this->assertTrue((bool) $form->paymentConfig()['enabled']);
        $this->assertSame('per_field', $form->paymentMode());
        $this->assertSame(1000, $form->paymentAmountCents());
    }

    public function test_update_payment_allows_per_field_enable_with_priced_field_and_no_base_fee(): void
    {
        $owner = $this->user($this->plan(['paid_forms' => true]));
        CreatorPaymentConnection::create([
            'user_id' => $owner->id, 'provider' => 'stripe', 'account_id' => 'acct_test',
            'status' => 'active', 'payouts_enabled' => true, 'charges_enabled' => true,
            'is_default' => true,
        ]);

        // A priced field is itself a valid source even with a zero base fee.
        $form = $owner->forms()->create([
            'title'  => 'Priced Form',
            'fields' => [
                ['id' => 'qty', 'type' => 'number', 'label' => 'Tickets', 'price_cents' => 500],
            ],
        ]);

        $resp = $this->actingAs($owner)->put(route('user.forms.payment.update', $form), [
            'enabled'  => '1',
            'mode'     => 'per_field',
            'amount'   => '0',
            'currency' => 'USD',
        ]);

        $resp->assertSessionHas('success');
        $form->refresh();
        $this->assertTrue((bool) $form->paymentConfig()['enabled']);
        $this->assertSame(0, $form->paymentAmountCents());
    }
}
