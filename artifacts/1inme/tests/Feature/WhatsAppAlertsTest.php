<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Services\Monetization\MonetizationCheckout;
use App\Services\WhatsApp\WhatsAppCloudApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks down the one-way outbound WhatsApp alerts (Task #2765):
 *   (a) the per-form "Notify me on WhatsApp" toggle, gated on the owner having
 *       a verified number, fires a WhatsApp ping on a (free) form submission;
 *   (b) the toggle is forced off — and never pings — when the owner has no
 *       verified number, or when it is left off;
 *   (c) the account-level "payment alerts on WhatsApp" preference fires for a
 *       payment event (here: a new subscriber) only when opted in.
 *
 * The WhatsAppCloudApi (which would call Meta) is replaced with a Mockery spy
 * so the suite never makes a live delivery call — we only assert the alert was
 * *attempted* with the creator's number, mirroring preview-mode behaviour.
 */
class WhatsAppAlertsTest extends TestCase
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

    private function user(?Plan $plan = null, array $settings = []): User
    {
        return User::create([
            'name'     => 'u' . Str::random(4),
            'email'    => 'u' . Str::random(8) . '@example.com',
            'password' => Hash::make('x'),
            'status'   => 'active',
            'handle'   => 'h' . Str::lower(Str::random(10)),
            'plan_id'  => $plan?->id,
            'settings' => $settings,
            'onboarded_at' => now(),
        ]);
    }

    /** Attach a verified WhatsApp (phone) number to $u. */
    private function verifyWhatsapp(User $u, string $number = '+15551234567'): void
    {
        LinkedIdentifier::create([
            'user_id'     => $u->id,
            'kind'        => 'phone',
            'value'       => $number,
            'verified_at' => now(),
            'is_primary'  => true,
        ]);
    }

    /** A free form owned by $u with the WhatsApp alert toggle in $enabled. */
    private function form(User $u, bool $whatsappEnabled): Form
    {
        $form = new Form([
            'slug'    => 'wf-' . Str::lower(Str::random(8)),
            'title'   => 'Contact form',
            'fields'  => [
                ['id' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => false],
            ],
            'settings'      => Form::defaultSettings(),
            'notifications' => array_replace_recursive(Form::defaultNotifications(), [
                'whatsapp' => ['enabled' => $whatsappEnabled],
            ]),
            'is_active' => true,
        ]);
        $form->user_id = $u->id;
        $form->save();

        return $form;
    }

    /** Bind a WhatsApp Cloud API spy and return it. */
    private function whatsappSpy(): \Mockery\MockInterface
    {
        $spy = \Mockery::spy(WhatsAppCloudApi::class);
        $spy->shouldReceive('sendText')->andReturn(true)->byDefault();
        $this->app->instance(WhatsAppCloudApi::class, $spy);

        return $spy;
    }

    // ---------------------------------------------------------------------
    // (a) per-form toggle fires on submission
    // ---------------------------------------------------------------------

    public function test_form_submission_pings_whatsapp_when_enabled_and_number_verified(): void
    {
        $owner = $this->user($this->plan());
        $this->verifyWhatsapp($owner, '+15551112222');
        $form = $this->form($owner, true);

        $spy = $this->whatsappSpy();

        $this->postJson('/f/' . $form->slug, ['name' => 'Visitor', '_hp' => ''])->assertOk();

        $spy->shouldHaveReceived('sendText')
            ->with('+15551112222', \Mockery::type('string'))
            ->once();
    }

    public function test_form_submission_does_not_ping_when_toggle_off(): void
    {
        $owner = $this->user($this->plan());
        $this->verifyWhatsapp($owner);
        $form = $this->form($owner, false);

        $spy = $this->whatsappSpy();

        $this->postJson('/f/' . $form->slug, ['name' => 'Visitor', '_hp' => ''])->assertOk();

        $spy->shouldNotHaveReceived('sendText');
    }

    public function test_form_submission_does_not_ping_when_owner_has_no_number(): void
    {
        $owner = $this->user($this->plan()); // no verified WhatsApp number
        $form = $this->form($owner, true);   // toggle on in stored config

        $spy = $this->whatsappSpy();

        $this->postJson('/f/' . $form->slug, ['name' => 'Visitor', '_hp' => ''])->assertOk();

        // No number on file ⇒ the alert is skipped, never sent.
        $spy->shouldNotHaveReceived('sendText');
    }

    // ---------------------------------------------------------------------
    // (b) per-form toggle is forced off without a verified number
    // ---------------------------------------------------------------------

    public function test_update_notifications_forces_whatsapp_off_without_number(): void
    {
        $owner = $this->user($this->plan()); // no verified number
        $form = $this->form($owner, false);

        $this->actingAs($owner, 'web')
            ->put('/user/forms/' . $form->id . '/notifications', [
                'whatsapp_enabled' => '1',
            ])->assertRedirect();

        $this->assertFalse(
            (bool) ($form->fresh()->notifications['whatsapp']['enabled'] ?? false),
            'toggle must not persist as enabled without a verified number',
        );
    }

    public function test_update_notifications_keeps_whatsapp_on_with_number(): void
    {
        $owner = $this->user($this->plan());
        $this->verifyWhatsapp($owner);
        $form = $this->form($owner, false);

        $this->actingAs($owner, 'web')
            ->put('/user/forms/' . $form->id . '/notifications', [
                'whatsapp_enabled' => '1',
            ])->assertRedirect();

        $this->assertTrue(
            (bool) ($form->fresh()->notifications['whatsapp']['enabled'] ?? false),
            'a verified owner can turn the toggle on',
        );
    }

    // ---------------------------------------------------------------------
    // (c) account-level payment alerts
    // ---------------------------------------------------------------------

    public function test_payment_alert_fires_for_subscriber_when_opted_in(): void
    {
        $creator = $this->user($this->plan(), ['whatsapp_payment_alerts' => true]);
        $this->verifyWhatsapp($creator, '+15553334444');
        $fan = $this->user($this->plan());
        $tier = SubscriptionTier::create([
            'user_id'      => $creator->id,
            'name'         => 'Gold',
            'price_monthly_cents' => 500,
            'currency'     => 'USD',
            'is_active'    => true,
        ]);

        $spy = $this->whatsappSpy();

        $this->invokeNotifySubscriber($creator, $fan, $tier);

        $spy->shouldHaveReceived('sendText')
            ->with('+15553334444', \Mockery::type('string'))
            ->once();
    }

    public function test_payment_alert_skipped_when_not_opted_in(): void
    {
        $creator = $this->user($this->plan()); // preference off (default)
        $this->verifyWhatsapp($creator);
        $fan = $this->user($this->plan());
        $tier = SubscriptionTier::create([
            'user_id'      => $creator->id,
            'name'         => 'Gold',
            'price_monthly_cents' => 500,
            'currency'     => 'USD',
            'is_active'    => true,
        ]);

        $spy = $this->whatsappSpy();

        $this->invokeNotifySubscriber($creator, $fan, $tier);

        $spy->shouldNotHaveReceived('sendText');
    }

    public function test_payment_alert_skipped_when_opted_in_but_no_verified_number(): void
    {
        // Opted in at the account level, but never verified a WhatsApp number ⇒
        // the alert resolver finds no destination and the send is skipped.
        $creator = $this->user($this->plan(), ['whatsapp_payment_alerts' => true]);
        $fan = $this->user($this->plan());
        $tier = SubscriptionTier::create([
            'user_id'      => $creator->id,
            'name'         => 'Gold',
            'price_monthly_cents' => 500,
            'currency'     => 'USD',
            'is_active'    => true,
        ]);

        $spy = $this->whatsappSpy();

        $this->invokeNotifySubscriber($creator, $fan, $tier);

        $spy->shouldNotHaveReceived('sendText');
    }

    public function test_update_preferences_persists_opt_in_with_number(): void
    {
        $user = $this->user($this->plan());
        $this->verifyWhatsapp($user);

        $this->actingAs($user, 'web')
            ->put('/user/notifications/preferences', [
                'prefs'                    => [],
                'whatsapp_payment_alerts'  => '1',
            ])->assertRedirect();

        $this->assertTrue($user->fresh()->wantsWhatsappPaymentAlerts());
    }

    public function test_update_preferences_forces_opt_in_off_without_number(): void
    {
        $user = $this->user($this->plan()); // no verified number

        $this->actingAs($user, 'web')
            ->put('/user/notifications/preferences', [
                'prefs'                    => [],
                'whatsapp_payment_alerts'  => '1',
            ])->assertRedirect();

        $this->assertFalse($user->fresh()->wantsWhatsappPaymentAlerts());
    }

    /**
     * Call the protected MonetizationCheckout::notifyCreatorOfNewSubscriber via
     * reflection — it is the payment fan-out path that fires the WhatsApp alert.
     */
    private function invokeNotifySubscriber(User $creator, User $fan, SubscriptionTier $tier): void
    {
        $svc = app(MonetizationCheckout::class);
        $ref = new \ReflectionMethod($svc, 'notifyCreatorOfNewSubscriber');
        $ref->setAccessible(true);
        $ref->invoke($svc, $creator, $fan, $tier);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
