<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\User;
use App\Modules\User\Services\InboxForwarder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks down the paid-forms payment flow (Task #2319) — money, plan gating
 * and the deferred gateway-return reconcile that only counts a submission +
 * fires owner notifications once the charge has cleared.
 *
 * Asserts that:
 *   (a) enabling paid forms is blocked below Pro and allowed at Pro+ (web-
 *       facing rule via Form::isPaid + the mobile API updatePayment gate);
 *   (b) a paid submission stays `pending` at submit — NOT counted, NO ledger
 *       event, NO owner notification — and is finalized (paid, counted,
 *       ledger event written, owner fan-out fired) only on a verified return;
 *   (c) re-delivery of the same verified return is idempotent — the
 *       `payment_status === 'paid'` guard prevents a double count / double
 *       credit / double notification;
 *   (d) the mobile API payment GET/PUT + analytics endpoints honor the same
 *       `paid_forms` / `form_analytics_advanced` plan gating.
 *
 * Sanctum API calls use a REAL bearer token (createToken()->plainTextToken),
 * never Sanctum::actingAs — actingAs breaks the TouchSessionToken middleware
 * (see .agents/memory/sanctum-api-tests.md).
 */
class PaidFormsFlowTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $features = [], string $name = 'Free'): Plan
    {
        return Plan::create([
            'name'          => $name,
            'slug'          => Str::slug($name) . '-' . Str::random(6),
            'description'   => $name,
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 1,
            'features'      => $features,
        ]);
    }

    private function proPlan(): Plan
    {
        return $this->plan(['paid_forms' => true, 'form_analytics_advanced' => true], 'Pro');
    }

    private function user(?Plan $plan = null): User
    {
        return User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'handle'   => 'h' . Str::lower(Str::random(10)),
            'plan_id'  => $plan?->id,
        ]);
    }

    /** Give the owner a default connected gateway so funds have a destination. */
    private function gateway(User $u): CreatorPaymentConnection
    {
        return CreatorPaymentConnection::create([
            'user_id'         => $u->id,
            'provider'        => 'stripe',
            'account_id'      => 'acct_' . Str::random(8),
            'status'          => 'active',
            'payouts_enabled' => true,
            'charges_enabled' => true,
            'is_default'      => true,
        ]);
    }

    /** A paid form owned by $u, charging $amountCents in $currency. */
    private function paidForm(User $u, int $amountCents = 1500, string $currency = 'USD'): Form
    {
        $form = new Form([
            'slug'     => 'pf-' . Str::lower(Str::random(8)),
            'title'    => 'Paid Form',
            'fields'   => [
                ['id' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => false],
            ],
            'settings' => array_merge(Form::defaultSettings(), [
                'payment' => array_merge(Form::defaultSettings()['payment'], [
                    'enabled'      => true,
                    'mode'         => 'fixed',
                    'amount_cents' => $amountCents,
                    'currency'     => $currency,
                ]),
            ]),
            'is_active' => true,
        ]);
        $form->user_id = $u->id;
        $form->save();

        return $form;
    }

    /** Submit the public form as JSON and return the decoded response. */
    private function submitPublic(Form $form, array $data = ['name' => 'Buyer'])
    {
        return $this->postJson('/f/' . $form->slug, $data + ['_hp' => '']);
    }

    /** Parse the reference + token out of a returned checkout URL. */
    private function refAndToken(string $checkoutUrl): array
    {
        parse_str((string) parse_url($checkoutUrl, PHP_URL_QUERY), $q);
        return [$q['reference'] ?? '', $q['token'] ?? ''];
    }

    // ---------------------------------------------------------------------
    // (a) Plan gating for enabling paid forms
    // ---------------------------------------------------------------------

    public function test_form_is_not_paid_when_owner_is_below_pro(): void
    {
        $free = $this->user($this->plan()); // no paid_forms feature
        $this->gateway($free);
        $form = $this->paidForm($free);

        // Toggle is on + amount > 0, but the plan doesn't permit paid forms,
        // so the form silently degrades to a free submission at submit time.
        $this->assertFalse($form->isPaid(), 'below-Pro owner must not run a paid form');
    }

    public function test_form_is_paid_when_owner_is_pro(): void
    {
        $pro  = $this->user($this->proPlan());
        $this->gateway($pro);
        $form = $this->paidForm($pro);

        $this->assertTrue($form->isPaid(), 'Pro owner with a positive price runs a paid form');
    }

    public function test_mobile_update_payment_blocked_below_pro(): void
    {
        $free = $this->user($this->plan());
        $this->gateway($free);
        $form = $this->paidForm($free);
        // Reset stored settings to a clean free form so we observe the gate,
        // not the pre-seeded paid config.
        $form->update(['settings' => Form::defaultSettings()]);

        $this->withToken($free->createToken('m')->plainTextToken);
        $resp = $this->putJson('/api/v1/forms/' . $form->id . '/payment', [
            'enabled'  => true,
            'amount'   => 12.00,
            'currency' => 'USD',
        ]);

        $resp->assertStatus(403);
        $this->assertFalse((bool) ($form->fresh()->paymentConfig()['enabled'] ?? false),
            'a blocked request must not persist an enabled price');
    }

    public function test_mobile_update_payment_allowed_at_pro(): void
    {
        $pro = $this->user($this->proPlan());
        $this->gateway($pro);
        $form = $this->paidForm($pro);
        $form->update(['settings' => Form::defaultSettings()]);

        $this->withToken($pro->createToken('m')->plainTextToken);
        $resp = $this->putJson('/api/v1/forms/' . $form->id . '/payment', [
            'enabled'  => true,
            'amount'   => 12.50,
            'currency' => 'usd',
        ]);

        $resp->assertOk();
        $cfg = $form->fresh()->paymentConfig();
        $this->assertTrue((bool) $cfg['enabled']);
        $this->assertSame(1250, (int) $cfg['amount_cents']);
        $this->assertSame('USD', $cfg['currency']);
    }

    public function test_mobile_update_payment_requires_connected_gateway_at_pro(): void
    {
        $pro = $this->user($this->proPlan()); // Pro but NO gateway
        $form = $this->paidForm($pro);
        $form->update(['settings' => Form::defaultSettings()]);

        $this->withToken($pro->createToken('m')->plainTextToken);
        $resp = $this->putJson('/api/v1/forms/' . $form->id . '/payment', [
            'enabled'  => true,
            'amount'   => 5.00,
            'currency' => 'USD',
        ]);

        $resp->assertStatus(422);
        $this->assertFalse((bool) ($form->fresh()->paymentConfig()['enabled'] ?? false));
    }

    public function test_mobile_payment_get_reports_plan_and_gateway_state(): void
    {
        $pro = $this->user($this->proPlan());
        $this->gateway($pro);
        $form = $this->paidForm($pro);

        $this->withToken($pro->createToken('m')->plainTextToken);
        $this->getJson('/api/v1/forms/' . $form->id . '/payment')
            ->assertOk()
            ->assertJsonPath('data.can_paid_forms', true)
            ->assertJsonPath('data.has_gateway', true);

        $free = $this->user($this->plan());
        $freeForm = $this->paidForm($free);
        // Reset resolved guards so the next request re-authenticates from the
        // new bearer token instead of reusing the first user cached on the
        // singleton sanctum guard within this test.
        $this->app['auth']->forgetGuards();
        $this->withToken($free->createToken('m')->plainTextToken);
        $this->getJson('/api/v1/forms/' . $freeForm->id . '/payment')
            ->assertOk()
            ->assertJsonPath('data.can_paid_forms', false)
            ->assertJsonPath('data.has_gateway', false);
    }

    public function test_mobile_analytics_honors_plan_gating(): void
    {
        $free = $this->user($this->plan());
        $freeForm = $this->paidForm($free);
        $this->withToken($free->createToken('m')->plainTextToken);
        $this->getJson('/api/v1/forms/' . $freeForm->id . '/analytics')->assertStatus(403);

        $pro = $this->user($this->proPlan());
        $proForm = $this->paidForm($pro);
        // Reset resolved guards so the next request re-authenticates from the
        // new bearer token instead of reusing the first user cached on the
        // singleton sanctum guard within this test.
        $this->app['auth']->forgetGuards();
        $this->withToken($pro->createToken('m')->plainTextToken);
        $this->getJson('/api/v1/forms/' . $proForm->id . '/analytics')->assertOk();
    }

    // ---------------------------------------------------------------------
    // (b) Pending at submit, finalized only on verified return
    // ---------------------------------------------------------------------

    public function test_paid_submission_is_pending_then_finalized_on_return(): void
    {
        $pro = $this->user($this->proPlan());
        $this->gateway($pro);
        $form = $this->paidForm($pro, 1500, 'USD');

        // Spy the account-level forwarder: it is the deferred owner fan-out
        // that confirmFormPayment -> finalizePaidSubmission triggers. It must
        // NOT fire while the charge is still pending.
        $forwarder = \Mockery::spy(InboxForwarder::class);
        $this->app->instance(InboxForwarder::class, $forwarder);

        $resp = $this->submitPublic($form);
        $resp->assertOk()
            ->assertJsonPath('payment_required', true)
            ->assertJsonPath('amount_cents', 1500)
            ->assertJsonPath('currency', 'USD');

        $submission = FormSubmission::withoutGlobalScope('workspace')
            ->where('form_id', $form->id)->firstOrFail();

        // Pending: held back from counting, ledger and notifications.
        $this->assertSame('pending', $submission->payment_status);
        $this->assertSame(0, (int) $form->fresh()->total_submissions, 'pending attempt must not be counted');
        $this->assertSame(0, CreatorPaymentEvent::where('creator_user_id', $pro->id)->count(),
            'no revenue logged before the charge clears');
        $forwarder->shouldNotHaveReceived('dispatchForFormSubmission');

        // Verified gateway return reconciles the submission.
        [$reference, $token] = $this->refAndToken($resp->json('checkout_url'));
        $this->assertNotEmpty($token, 'checkout URL must carry the reconcile token');

        $this->get('/checkout/return?kind=form&reference=' . $reference . '&token=' . $token)
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame('paid', $submission->payment_status);
        $this->assertNotNull($submission->paid_at);
        $this->assertSame(1, (int) $form->fresh()->total_submissions, 'paid submission is counted exactly once');

        // Ledger event: source=form, type=form.paid, amount carried through.
        $event = CreatorPaymentEvent::where('creator_user_id', $pro->id)->firstOrFail();
        $this->assertSame(CreatorPaymentEvent::SOURCE_FORM, $event->source);
        $this->assertSame(CreatorPaymentEvent::TYPE_FORM_PAID, $event->type);
        $this->assertSame(1500, (int) $event->amount_cents);

        // Owner fan-out fired exactly once, only after payment cleared.
        $forwarder->shouldHaveReceived('dispatchForFormSubmission')->once();
    }

    // ---------------------------------------------------------------------
    // (c) Re-delivery of the same return is idempotent
    // ---------------------------------------------------------------------

    public function test_redelivered_return_does_not_double_count_or_double_notify(): void
    {
        $pro = $this->user($this->proPlan());
        $this->gateway($pro);
        $form = $this->paidForm($pro, 2000, 'USD');

        $forwarder = \Mockery::spy(InboxForwarder::class);
        $this->app->instance(InboxForwarder::class, $forwarder);

        $resp = $this->submitPublic($form);
        $resp->assertOk();
        $submission = FormSubmission::withoutGlobalScope('workspace')
            ->where('form_id', $form->id)->firstOrFail();

        [$reference, $token] = $this->refAndToken($resp->json('checkout_url'));

        // Capture the real cached reconcile payload BEFORE the first return
        // consumes (pulls) it, so we can replay an identical delivery.
        $cacheKey = 'monetization_checkout:form:' . $submission->id . ':' . $token;
        $payload  = cache()->get($cacheKey);
        $this->assertNotNull($payload, 'reconcile payload must be cached at submit');

        $url = '/checkout/return?kind=form&reference=' . $reference . '&token=' . $token;

        // First verified return finalizes.
        $this->get($url)->assertRedirect();
        $this->assertSame('paid', $submission->fresh()->payment_status);
        $this->assertSame(1, (int) $form->fresh()->total_submissions);

        // Re-deliver: re-seed an identical payload (simulating the provider
        // sending the return again) and hit the route a second time. The
        // payment_status === 'paid' guard must short-circuit.
        cache()->put($cacheKey, $payload, now()->addMinutes(30));
        $this->get($url)->assertRedirect();

        $this->assertSame(1, (int) $form->fresh()->total_submissions, 'no double count on re-delivery');
        $this->assertSame(1, CreatorPaymentEvent::where('creator_user_id', $pro->id)->count(),
            'no double credit (single ledger event) on re-delivery');
        $forwarder->shouldHaveReceived('dispatchForFormSubmission')->once();
    }
}
