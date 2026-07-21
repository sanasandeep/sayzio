<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Web remove flow for a connected WhatsApp number (Task #5540).
 *
 * The Profile Settings WhatsApp card supports add/change but historically had
 * no "remove" action. POST /user/onboarding/whatsapp/remove must unlink the
 * verified phone via AccountMergeService::unlink, and — when the phone is the
 * primary identifier — promote another verified email/phone to primary first
 * (unlink refuses to drop a primary). It must never leave the account with no
 * verified email or phone.
 */
class WhatsAppRemoveWebTest extends TestCase
{
    use RefreshDatabase;

    private function plan(): Plan
    {
        return Plan::create([
            'name'          => 'Free',
            'slug'          => 'p-' . Str::lower(Str::random(8)),
            'monthly_price' => 0,
            'annual_price'  => 0,
            'trial_days'    => 0,
            'status'        => 'active',
            'sort_order'    => 1,
            'features'      => ['max_links' => 100, 'max_biolinks' => 100],
        ]);
    }

    /**
     * User::created auto-mirrors users.email into a verified PRIMARY email
     * linked identifier, so tests must fetch that row instead of minting a
     * second primary (one_primary_per_user).
     */
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
            'onboarded_at' => now(),
        ]);
        return $user->fresh();
    }

    private function verifyPhone(User $u, string $number = '+15551234567', bool $primary = false): LinkedIdentifier
    {
        if ($primary) {
            $u->linkedIdentifiers()->update(['is_primary' => false]);
        }
        return LinkedIdentifier::create([
            'user_id'     => $u->id,
            'kind'        => 'phone',
            'value'       => $number,
            'verified_at' => now(),
            'is_primary'  => $primary,
        ]);
    }

    public function test_remove_unlinks_a_non_primary_number(): void
    {
        $user  = $this->user();
        $phone = $this->verifyPhone($user);

        $this->be($user, 'web')
            ->post(route('user.onboarding.whatsapp.remove'))
            ->assertRedirect(route('user.profile.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('linked_identifiers', ['id' => $phone->id]);
        $this->assertFalse($user->fresh()->hasWhatsappNumber());
    }

    public function test_remove_promotes_email_when_phone_is_primary(): void
    {
        $user  = $this->user();
        $phone = $this->verifyPhone($user, '+15551234567', primary: true);
        $email = $user->linkedIdentifiers()->where('kind', 'email')->firstOrFail();

        $this->be($user, 'web')
            ->post(route('user.onboarding.whatsapp.remove'))
            ->assertRedirect(route('user.profile.edit'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('linked_identifiers', ['id' => $phone->id]);
        $this->assertTrue((bool) $email->fresh()->is_primary);
    }

    public function test_remove_refuses_when_phone_is_the_only_verified_identifier(): void
    {
        $user  = $this->user();
        $phone = $this->verifyPhone($user, '+15551234567', primary: true);
        // Un-verify the auto-created email so the phone is the only contact.
        $user->linkedIdentifiers()->where('kind', 'email')->update(['verified_at' => null]);

        $this->be($user, 'web')
            ->post(route('user.onboarding.whatsapp.remove'))
            ->assertRedirect(route('user.profile.edit'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('linked_identifiers', ['id' => $phone->id]);
    }

    public function test_remove_without_a_number_errors_gracefully(): void
    {
        $user = $this->user();

        $this->be($user, 'web')
            ->post(route('user.onboarding.whatsapp.remove'))
            ->assertRedirect(route('user.profile.edit'))
            ->assertSessionHas('error');
    }

    public function test_settings_card_shows_remove_button_only_when_connected(): void
    {
        $user = $this->user();

        $this->be($user, 'web')
            ->get(route('user.profile.edit'))
            ->assertOk()
            ->assertDontSee('Remove number');

        $this->verifyPhone($user);

        $this->be($user, 'web')
            ->get(route('user.profile.edit'))
            ->assertOk()
            ->assertSee('Remove number')
            ->assertSee(route('user.onboarding.whatsapp.remove'));
    }
}
