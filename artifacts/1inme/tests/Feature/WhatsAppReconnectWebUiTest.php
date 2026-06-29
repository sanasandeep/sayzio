<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Web-side reverse of the WhatsApp disconnect UI coverage (Task #2786,
 * mirror of WhatsAppDisconnectWebUiTest from Task #2784).
 *
 * Re-connecting a verified WhatsApp number must switch its dependent alerts
 * back ON on the web, because every WhatsApp surface is gated on
 * User::hasWhatsappNumber():
 *   - the account-level "WhatsApp payment alerts" toggle in
 *     /user/notifications/preferences
 *   - each form's "WhatsApp alert" toggle in /user/forms/{id}/notifications
 *
 * The disconnect direction (toggles disappear, "Connect WhatsApp" prompt
 * returns) is already covered. What had no coverage was the *web UI wiring
 * after a (re)connect*: once a verified number is present again — here driven
 * through the real inline WhatsApp connect/verify controller path that the
 * onboarding step and dashboard nudge both use — the payment-alert toggle and
 * the per-form toggle must reappear and the "Connect and verify a WhatsApp
 * number..." prompt must be gone. A regression there would leave a user unable
 * to re-enable alerts even after adding a valid number.
 *
 * This renders the REAL Blade views via HTTP across the disconnected →
 * connected transition so the gating can't drift from the templates, and it
 * drives the connect through the actual whatsappVerify endpoint rather than
 * hand-inserting the identifier, so the controller wiring is exercised too.
 */
class WhatsAppReconnectWebUiTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
    {
        return Plan::create([
            'name'          => 'Free',
            'slug'          => 'free-' . Str::random(6),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 1,
            'features'      => ['max_links' => 100, 'max_biolinks' => 100],
        ]);
    }

    private function user(): User
    {
        $user = User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@example.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'role'         => 'user',
            'handle'       => 'h' . Str::lower(Str::random(10)),
            'plan_id'      => $this->plan()->id,
            'settings'     => ['whatsapp_payment_alerts' => true],
            'onboarded_at' => now(),
        ]);
        return $user->fresh();
    }

    /** Keep a verified primary email on file so the account always has a contact. */
    private function verifyEmail(User $u): LinkedIdentifier
    {
        return LinkedIdentifier::create([
            'user_id'     => $u->id,
            'kind'        => 'email',
            'value'       => $u->email,
            'verified_at' => now(),
            'is_primary'  => true,
        ]);
    }

    private function form(User $u): Form
    {
        $form = new Form([
            'slug'    => 'wf-' . Str::lower(Str::random(8)),
            'title'   => 'Contact form',
            'fields'  => [
                ['id' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => false],
            ],
            'settings'      => Form::defaultSettings(),
            'notifications' => array_replace_recursive(Form::defaultNotifications(), [
                'whatsapp' => ['enabled' => true],
            ]),
            'is_active' => true,
        ]);
        $form->user_id = $u->id;
        $form->save();

        return $form;
    }

    public function test_reconnect_turns_on_account_and_per_form_whatsapp_alerts_on_the_web(): void
    {
        $user = $this->user();
        $this->verifyEmail($user);
        $form = $this->form($user);

        // No number on file yet — every WhatsApp surface starts gated off.
        $this->assertFalse($user->hasWhatsappNumber());

        // ---- While disconnected: account toggle gone, verify prompt shown ----
        $this->actingAs($user, 'web')
            ->get('/user/notifications/preferences')
            ->assertOk()
            ->assertDontSee('name="whatsapp_payment_alerts"', false)
            ->assertSee('Connect and verify a WhatsApp number on your account to enable payment alerts.')
            ->assertSee('Connect WhatsApp');

        // ---- While disconnected: per-form toggle gone, verify prompt shown ---
        $this->actingAs($user, 'web')
            ->get('/user/forms/' . $form->id . '/notifications')
            ->assertOk()
            ->assertDontSee('name="whatsapp_enabled"', false)
            ->assertSee('Connect and verify a WhatsApp number on your account to turn this on.')
            ->assertSee('Connect WhatsApp');

        // ---- Run the real inline WhatsApp connect/verify path ----------------
        // Issue a code for the number, then verify it through the controller the
        // onboarding step + dashboard nudge use. The pending number normally
        // lands in the session via whatsappSend; seed it the same way so verify
        // can attach the identifier.
        $number = '+15551234567';
        $value  = LinkedIdentifier::normalize('phone', $number);
        $code   = (new OtpService())->generate($value, 'mobile', 'link', 'web');

        $this->actingAs($user, 'web')
            ->withSession(['whatsapp_connect_pending' => $value])
            ->post('/user/onboarding/whatsapp/verify', ['code' => $code])
            ->assertRedirect();

        $this->assertDatabaseHas('linked_identifiers', [
            'user_id' => $user->id,
            'kind'    => 'phone',
            'value'   => $value,
        ]);
        $this->assertNotNull(
            LinkedIdentifier::where('user_id', $user->id)->where('kind', 'phone')->first()?->verified_at
        );
        $this->assertTrue($user->fresh()->hasWhatsappNumber());

        // ---- After reconnect: account toggle back, prompt gone --------------
        $this->actingAs($user, 'web')
            ->get('/user/notifications/preferences')
            ->assertOk()
            ->assertSee('name="whatsapp_payment_alerts"', false)
            ->assertSee('Send me payment alerts on WhatsApp')
            ->assertDontSee('to enable payment alerts.');

        // ---- After reconnect: per-form toggle back, prompt gone -------------
        $this->actingAs($user, 'web')
            ->get('/user/forms/' . $form->id . '/notifications')
            ->assertOk()
            ->assertSee('name="whatsapp_enabled"', false)
            ->assertDontSee('to turn this on.');
    }
}
