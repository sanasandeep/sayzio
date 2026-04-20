<?php

namespace Tests\Feature;

use App\Events\SubscriptionActivated;
use App\Listeners\IssueInvoiceOnSubscriptionActivated;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionActivationInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::create([
            'name'     => 'Buyer ' . Str::random(4),
            'email'    => 'b' . Str::random(8) . '@ex.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'country'  => 'IN',
        ]);
    }

    public function test_subscription_activated_listener_issues_invoice_with_sequential_number(): void
    {
        $user = $this->makeUser();
        BillingAddress::create([
            'user_id'     => $user->id,
            'country'     => 'IN',
            'region'      => 'MH',
            'postal_code' => '400001',
            'line1'       => '1 Test Rd',
            'city'        => 'Mumbai',
        ]);

        $listener = new IssueInvoiceOnSubscriptionActivated();

        $invoice1 = $listener->handle(new SubscriptionActivated(
            $user,
            [['label' => 'Pro (monthly)', 'amount_minor' => 99900, 'quantity' => 1]],
            'INR',
        ));
        $invoice2 = $listener->handle(new SubscriptionActivated(
            $user,
            [['label' => 'Pro (monthly)', 'amount_minor' => 99900, 'quantity' => 1]],
            'INR',
        ));

        $this->assertInstanceOf(Invoice::class, $invoice1);
        $this->assertInstanceOf(Invoice::class, $invoice2);
        $this->assertSame(2, Invoice::where('user_id', $user->id)->count());
        $this->assertSame($invoice1->seq + 1, $invoice2->seq);
        $this->assertStringContainsString('INV/', $invoice1->number);
        $this->assertGreaterThan(0, $invoice1->tax_total_minor);
        $this->assertSame($invoice1->subtotal_minor + $invoice1->tax_total_minor, $invoice1->grand_total_minor);
    }

    public function test_listener_still_issues_invoice_when_no_billing_address(): void
    {
        $user = $this->makeUser();
        $listener = new IssueInvoiceOnSubscriptionActivated();

        $invoice = $listener->handle(new SubscriptionActivated(
            $user,
            [['label' => 'Pro (monthly)', 'amount_minor' => 99900, 'quantity' => 1]],
            'INR',
        ));

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(1, Invoice::where('user_id', $user->id)->count());
        $this->assertSame($user->name, $invoice->billing_address_snapshot['buyer_name'] ?? null);
    }

    public function test_activate_endpoint_rejects_non_privileged_users(): void
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-' . Str::random(4), 'description' => 'Pro',
            'monthly_price' => 9.99, 'annual_price' => 99, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);
        $user = $this->makeUser();

        $response = $this->actingAs($user)->post('/user/upgrade/activate', [
            'plan_id' => $plan->id,
            'cycle'   => 'monthly',
        ]);

        $response->assertForbidden();
        $this->assertNull($user->fresh()->plan_id);
        $this->assertSame(0, Invoice::where('user_id', $user->id)->count());
    }

    public function test_activate_endpoint_allows_super_admin_and_creates_invoice(): void
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-' . Str::random(4), 'description' => 'Pro',
            'monthly_price' => 9.99, 'annual_price' => 99, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);

        $buyer = $this->makeUser();
        BillingAddress::create([
            'user_id' => $buyer->id, 'country' => 'IN', 'region' => 'MH',
            'postal_code' => '400001', 'line1' => '1 Rd', 'city' => 'Mumbai',
        ]);

        $admin = $this->makeUser();
        $admin->forceFill(['role' => 'super_admin'])->save();

        $response = $this->actingAs($admin)->post('/user/upgrade/activate', [
            'user_id'     => $buyer->id,
            'plan_id'     => $plan->id,
            'cycle'       => 'monthly',
            'gateway_ref' => 'pay_test_abc',
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Invoice::where('user_id', $buyer->id)->count());
        $this->assertSame($plan->id, $buyer->fresh()->plan_id);
    }

    public function test_activate_endpoint_allows_signed_gateway_webhook(): void
    {
        config(['billing.activation_secret' => 'test-secret-xyz']);

        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-' . Str::random(4), 'description' => 'Pro',
            'monthly_price' => 9.99, 'annual_price' => 99, 'trial_days' => 0,
            'status' => 'active', 'sort_order' => 1, 'features' => [],
        ]);

        $buyer = $this->makeUser();
        BillingAddress::create([
            'user_id' => $buyer->id, 'country' => 'IN', 'region' => 'MH',
            'postal_code' => '400001', 'line1' => '1 Rd', 'city' => 'Mumbai',
        ]);

        $payload = [
            'user_id'     => $buyer->id,
            'plan_id'     => $plan->id,
            'cycle'       => 'monthly',
            'gateway_ref' => 'pay_live_abc',
        ];
        $payload['signature'] = hash_hmac('sha256',
            implode('|', [$payload['user_id'], $payload['plan_id'], $payload['cycle'], $payload['gateway_ref']]),
            'test-secret-xyz',
        );

        $response = $this->actingAs($buyer)->post('/user/upgrade/activate', $payload);

        $response->assertRedirect();
        $this->assertSame(1, Invoice::where('user_id', $buyer->id)->count());
        $this->assertSame($plan->id, $buyer->fresh()->plan_id);
    }
}
