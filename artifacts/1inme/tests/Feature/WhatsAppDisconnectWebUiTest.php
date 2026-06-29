<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Web-side mirror of the mobile WhatsApp disconnect UI coverage (Task #2784,
 * after Task #2780's mobile test-whatsapp-disconnect.mjs).
 *
 * Removing a WhatsApp number must turn its dependent alerts OFF on the web too,
 * because every WhatsApp surface is gated on User::hasWhatsappNumber():
 *   - the account-level "WhatsApp payment alerts" toggle in
 *     /user/notifications/preferences
 *   - each form's "WhatsApp alert" toggle in /user/forms/{id}/notifications
 *
 * The send-time backend behaviour is already covered (WhatsAppAlertsTest), and
 * the disconnect API/guard is covered (WhatsAppDisconnectApiTest). What had no
 * coverage was the *web UI wiring after a disconnect*: once the verified number
 * is removed through the Account Settings linked-identifier remove path, the
 * payment-alert toggle must disappear and the "Connect WhatsApp" verify prompt
 * must reappear, and the per-form toggle must likewise flip to the disabled
 * (prompt) state. A regression there would silently leave a web user believing
 * their alerts are still on after they removed their number.
 *
 * This renders the REAL Blade views via HTTP across the connected → disconnected
 * transition so the gating can't drift from the templates.
 */
class WhatsAppDisconnectWebUiTest extends TestCase
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

    /**
     * Keep a verified primary email on file so the WhatsApp phone is a
     * non-primary, removable identifier (mirrors the unlink guards that block
     * dropping a primary or the last verified contact).
     */
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

    private function verifyWhatsapp(User $u, string $number = '+15551234567'): LinkedIdentifier
    {
        return LinkedIdentifier::create([
            'user_id'     => $u->id,
            'kind'        => 'phone',
            'value'       => $number,
            'verified_at' => now(),
            'is_primary'  => false,
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

    public function test_disconnect_turns_off_account_and_per_form_whatsapp_alerts_on_the_web(): void
    {
        $user = $this->user();
        $this->verifyEmail($user);
        $phone = $this->verifyWhatsapp($user);
        $form = $this->form($user);

        $this->assertTrue($user->hasWhatsappNumber());

        // ---- While connected: both surfaces show the live toggle ----------
        $this->actingAs($user, 'web')
            ->get('/user/notifications/preferences')
            ->assertOk()
            ->assertSee('name="whatsapp_payment_alerts"', false)
            ->assertSee('Send me payment alerts on WhatsApp')
            ->assertDontSee('to enable payment alerts.');

        $this->actingAs($user, 'web')
            ->get('/user/forms/' . $form->id . '/notifications')
            ->assertOk()
            ->assertSee('name="whatsapp_enabled"', false)
            ->assertDontSee('to turn this on.');

        // ---- Run the web disconnect (Account Settings remove path) --------
        $this->actingAs($user, 'web')
            ->delete('/user/identifiers/' . $phone->id)
            ->assertRedirect();

        $this->assertDatabaseMissing('linked_identifiers', ['id' => $phone->id]);
        $this->assertFalse($user->fresh()->hasWhatsappNumber());

        // ---- After disconnect: account toggle gone, verify prompt back ----
        $this->actingAs($user, 'web')
            ->get('/user/notifications/preferences')
            ->assertOk()
            ->assertDontSee('name="whatsapp_payment_alerts"', false)
            ->assertSee('Connect and verify a WhatsApp number on your account to enable payment alerts.')
            ->assertSee('Connect WhatsApp');

        // ---- After disconnect: per-form toggle gone, verify prompt back ---
        $this->actingAs($user, 'web')
            ->get('/user/forms/' . $form->id . '/notifications')
            ->assertOk()
            ->assertDontSee('name="whatsapp_enabled"', false)
            ->assertSee('Connect and verify a WhatsApp number on your account to turn this on.')
            ->assertSee('Connect WhatsApp');
    }
}
