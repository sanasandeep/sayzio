<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscription;
use App\Modules\Admin\Models\GatewaySetting;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\PaymentAttempt;
use App\Modules\User\Models\Subscription;
use App\Modules\User\Models\User;
use App\Services\Billing\GatewayManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\GatewaySettingsSeeder::class);
    }

    protected function makeBuyer(?string $country = 'IN'): User
    {
        $u = User::create([
            'name' => 'Buyer '.Str::random(4),
            'email' => 'b'.Str::random(6).'@e.com',
            'password' => bcrypt('secret'),
            'country' => $country,
        ]);
        BillingAddress::create([
            'user_id' => $u->id, 'country' => $country, 'region' => 'MH',
            'postal_code' => '400001', 'line1' => '1 Rd', 'city' => 'Mumbai',
        ]);
        return $u;
    }

    protected function makePlan(float $monthly = 9.99): Plan
    {
        return Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.Str::random(4), 'description' => 'Pro',
            'monthly_price' => $monthly, 'annual_price' => $monthly * 10, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);
    }

    /**
     * Create an addon with explicit INR + USD prices and attach it to $plan
     * so it counts as eligible. $monthlyMinorInr is the per-unit price.
     */
    protected function makeAddon(Plan $plan, int $monthlyMinorInr, array $features = []): \App\Modules\Admin\Models\Addon
    {
        $addon = \App\Modules\Admin\Models\Addon::create([
            'name' => 'Extra '.Str::random(4), 'slug' => 'extra-'.Str::random(6),
            'description' => 'Extra', 'type' => 'recurring',
            'monthly_price' => $monthlyMinorInr / 100, 'annual_price' => ($monthlyMinorInr * 10) / 100,
            'features' => $features, 'status' => 'active', 'is_archived' => false, 'sort_order' => 1,
        ]);
        foreach (['USD', 'INR'] as $cur) {
            \App\Modules\Admin\Models\Price::create([
                'priceable_type' => \App\Modules\Admin\Models\Addon::class,
                'priceable_id' => $addon->id, 'currency' => $cur,
                'billing_cycle' => 'monthly', 'amount_minor_units' => $monthlyMinorInr, 'is_active' => true,
            ]);
            \App\Modules\Admin\Models\Price::create([
                'priceable_type' => \App\Modules\Admin\Models\Addon::class,
                'priceable_id' => $addon->id, 'currency' => $cur,
                'billing_cycle' => 'annual', 'amount_minor_units' => $monthlyMinorInr * 10, 'is_active' => true,
            ]);
        }
        $plan->addons()->attach($addon->id);
        return $addon;
    }

    public function test_handoff_bills_multiple_addons_at_quantity(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makePlan();
        // Plan needs an INR price row (makePlan doesn't create one).
        foreach (['USD', 'INR'] as $cur) {
            \App\Modules\Admin\Models\Price::create([
                'priceable_type' => Plan::class, 'priceable_id' => $plan->id,
                'currency' => $cur, 'billing_cycle' => 'monthly',
                'amount_minor_units' => 99900, 'is_active' => true,
            ]);
        }
        $addonA = $this->makeAddon($plan, 5000, ['max_biolinks_extra' => 5]);
        $addonB = $this->makeAddon($plan, 2000, ['max_links_extra' => 10]);

        $this->actingAs($user)->post('/user/checkout/handoff', [
            'gateway' => 'offline',
            'plan_id' => $plan->id,
            'cycle'   => 'monthly',
            'addons'  => [$addonA->id => 3, $addonB->id => 2],
        ])->assertOk();

        $invoice = Invoice::where('user_id', $user->id)->firstOrFail();
        $items = collect($invoice->line_items);
        $a = $items->firstWhere('meta.addon_id', $addonA->id);
        $b = $items->firstWhere('meta.addon_id', $addonB->id);
        $this->assertSame(5000, (int) $a['amount_minor'], 'addon line stores per-unit price');
        $this->assertSame(3, (int) $a['quantity']);
        $this->assertSame(3, (int) $a['meta']['qty']);
        $this->assertSame(2, (int) $b['quantity']);

        // Subscription persists each addon with its purchased quantity.
        $sub = Subscription::where('user_id', $user->id)->latest('id')->firstOrFail();
        $qtys = $sub->addons()->pluck('qty', 'addon_id');
        $this->assertSame(3, (int) $qtys[$addonA->id]);
        $this->assertSame(2, (int) $qtys[$addonB->id]);

        // Effective features merge plan + addons × qty: +5×3 biolinks, +10×2 links.
        $eff = \App\Services\EffectivePlanFeatures::for($user->fresh());
        $this->assertSame(15, (int) ($eff['max_biolinks'] ?? 0));
        $this->assertSame(20, (int) ($eff['max_links'] ?? 0));
    }

    public function test_handoff_drops_addons_not_attached_to_plan(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makePlan();
        foreach (['USD', 'INR'] as $cur) {
            \App\Modules\Admin\Models\Price::create([
                'priceable_type' => Plan::class, 'priceable_id' => $plan->id,
                'currency' => $cur, 'billing_cycle' => 'monthly',
                'amount_minor_units' => 99900, 'is_active' => true,
            ]);
        }
        // Addon created but NOT attached to the plan → ineligible.
        $orphan = \App\Modules\Admin\Models\Addon::create([
            'name' => 'Orphan', 'slug' => 'orphan-'.Str::random(6), 'description' => 'x',
            'type' => 'recurring', 'monthly_price' => 9.99, 'annual_price' => 99.9,
            'features' => ['max_links_extra' => 10], 'status' => 'active',
            'is_archived' => false, 'sort_order' => 1,
        ]);

        $this->actingAs($user)->post('/user/checkout/handoff', [
            'gateway' => 'offline', 'plan_id' => $plan->id, 'cycle' => 'monthly',
            'addons'  => [$orphan->id => 4],
        ])->assertOk();

        $sub = Subscription::where('user_id', $user->id)->latest('id')->firstOrFail();
        $this->assertSame(0, $sub->addons()->count(), 'ineligible addon must not be billed/granted');
    }

    public function test_gateway_manager_returns_only_enabled_adapters(): void
    {
        $mgr = app(GatewayManager::class);
        $adapters = $mgr->enabledAdapters();
        $slugs = array_map(fn($a) => $a->slug(), $adapters);
        $this->assertContains('offline', $slugs, 'offline ships enabled by default');
        $this->assertNotContains('stripe', $slugs, 'stripe is disabled until configured');
    }

    public function test_gateway_settings_encrypt_credentials_at_rest(): void
    {
        $row = GatewaySetting::where('gateway_slug', 'stripe')->first();
        $row->update(['credentials_encrypted' => ['secret_key' => 'sk_live_TOP_SECRET']]);

        $raw = \DB::table('gateway_settings')->where('id', $row->id)->value('credentials_encrypted');
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('sk_live_TOP_SECRET', (string) $raw,
            'stored credential bytes must not contain plaintext');

        $roundTrip = GatewaySetting::find($row->id)->credential('secret_key');
        $this->assertSame('sk_live_TOP_SECRET', $roundTrip);
    }

    public function test_stub_gateway_throws_not_implemented(): void
    {
        GatewaySetting::where('gateway_slug', 'stripe')->update(['is_enabled' => true]);
        $mgr = app(GatewayManager::class);
        $this->expectException(\App\Services\Billing\NotImplementedException::class);
        $mgr->for('stripe')->createCheckout(new Invoice());
    }

    public function test_offline_checkout_creates_pending_invoice_and_attempt(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makePlan();

        $response = $this->actingAs($user)->post('/user/checkout/handoff', [
            'gateway' => 'offline',
            'plan_id' => $plan->id,
            'cycle'   => 'monthly',
        ]);

        $response->assertOk();
        $invoice = Invoice::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('awaiting_admin_approval', $invoice->status);
        $this->assertSame('offline', $invoice->gateway);
        $this->assertNull($invoice->paid_at);
        $this->assertSame(1, PaymentAttempt::where('invoice_id', $invoice->id)->count());
    }

    public function test_activate_subscription_is_reentrant(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makePlan();

        $invoice = ActivateSubscription::issuePendingInvoice($user, [[
            'label' => $plan->name.' (monthly)',
            'amount_minor' => 999,
            'quantity' => 1,
            'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
        ]], 'INR');

        $activator = app(ActivateSubscription::class);
        $s1 = $activator->run($invoice->fresh(), 'offline', 'ref-1');
        $s2 = $activator->run($invoice->fresh(), 'offline', 'ref-1');

        $this->assertSame($s1->id, $s2->id, 'second call must return same subscription, not create a new one');
        $this->assertSame(1, Subscription::where('user_id', $user->id)->count());
        $this->assertSame($plan->id, $user->fresh()->plan_id);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->paid_at);
    }

    public function test_admin_mark_paid_activates_subscription(): void
    {
        $buyer = $this->makeBuyer();
        $plan  = $this->makePlan();
        $invoice = ActivateSubscription::issuePendingInvoice($buyer, [[
            'label' => 'Pro (monthly)', 'amount_minor' => 999, 'quantity' => 1,
            'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
        ]], 'INR');
        $invoice->forceFill(['status' => 'awaiting_admin_approval', 'gateway' => 'offline'])->save();

        $admin = User::create([
            'name' => 'Ops', 'email' => 'ops'.Str::random(5).'@e.com',
            'password' => bcrypt('secret'),
        ]);
        $userAdminRoleId = \Illuminate\Support\Facades\DB::table('roles')
            ->where('slug', 'user-admin')->where('guard', 'web')
            ->value('id');
        if ($userAdminRoleId) {
            $admin->roles()->syncWithoutDetaching([$userAdminRoleId]);
            $admin->flushPermissionCache();
        }

        // The admin approval queue is under admin.* routes whose controller
        // middleware expects the admin guard; we invoke the controller
        // directly to avoid booting the separate Admin auth stack in this
        // feature test.
        $request = \Illuminate\Http\Request::create('/admin/payments/'.$invoice->id.'/mark-paid', 'POST', [
            'reference' => 'TXN-42', 'note' => 'Bank confirmed',
        ]);
        $request->setUserResolver(fn () => $admin);

        $controller = new \App\Modules\Admin\Controllers\PendingPaymentController();
        $controller->markPaid($request, $invoice, app(ActivateSubscription::class));

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame($plan->id, $buyer->fresh()->plan_id);
        // ref is scoped to the invoice so reused bank references across
        // different invoices don't collide on the (gateway,ref) unique index.
        $this->assertSame(1, PaymentAttempt::where('gateway', 'offline')
            ->where('gateway_ref', 'inv'.$invoice->id.':TXN-42')->count());
    }

    public function test_handoff_rejects_disabled_gateway(): void
    {
        // Stripe ships disabled — a direct POST must not bypass the UI.
        $user = $this->makeBuyer();
        $plan = $this->makePlan();
        $this->actingAs($user)->post('/user/checkout/handoff', [
            'gateway' => 'stripe',
            'plan_id' => $plan->id,
            'cycle'   => 'monthly',
        ])->assertRedirect();
        $this->assertSame(0, Invoice::where('user_id', $user->id)->count());
    }

    public function test_activate_refuses_paid_invoice_without_subscription(): void
    {
        $user = $this->makeBuyer();
        $plan = $this->makePlan();
        $invoice = ActivateSubscription::issuePendingInvoice($user, [[
            'label' => 'Pro', 'amount_minor' => 999, 'quantity' => 1,
            'meta' => ['kind' => 'plan', 'plan_id' => $plan->id, 'cycle' => 'monthly'],
        ]], 'INR');
        // Simulate an inconsistent row: paid, but no subscription linked.
        $invoice->forceFill(['status' => 'paid', 'subscription_id' => null])->save();

        $this->expectException(\RuntimeException::class);
        app(ActivateSubscription::class)->run($invoice, 'offline', 'x');
    }

    public function test_webhook_unknown_gateway_returns_404(): void
    {
        $this->post('/webhooks/nope', [])->assertStatus(404);
    }

    public function test_webhook_uncredentialed_gateway_rejects_with_400(): void
    {
        // After task-197 all four gateways (razorpay, stripe, paypal,
        // cashfree) have real adapters; none throw NotImplementedException
        // from verifyWebhook. Instead, a webhook to an enabled gateway
        // whose credentials are missing must be rejected with
        // `invalid signature` (400), not silently accepted. This guards
        // against a regression where a missing webhook_id/client_secret
        // accidentally permits any payload to activate invoices.
        $row = GatewaySetting::where('gateway_slug', 'paypal')->first();
        $row->is_enabled = true;
        $row->credentials_encrypted = []; // intentionally blank
        $row->save();
        $r = $this->post('/webhooks/paypal', ['anything' => 1]);
        $r->assertStatus(400);
    }
}
