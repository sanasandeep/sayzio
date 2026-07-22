<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Services\OtpService;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * "Change number" from Profile Settings (Task #5541).
 *
 * OnboardingController::whatsappVerify supports a replace mode (seeded by
 * whatsappSend when the flow starts from Settings with replace=1): after the
 * new number is verified, the previously verified phone(s) are retired via
 * AccountMergeService — promoteToPrimary first when the old phone was the
 * primary identifier (unlink refuses to remove a primary), then unlink.
 *
 * This must never leave the account unable to log in: after the swap there
 * must still be exactly one primary, verified identifier. These tests drive
 * the real /user/onboarding/whatsapp/verify endpoint across the three
 * shapes of the flow:
 *   1. old phone WAS the primary identifier → new phone becomes primary,
 *      old phone row is gone;
 *   2. old phone was NOT primary (email primary) → email stays primary,
 *      old phone row is gone;
 *   3. no replace flag → old phone is kept (plain add, nothing removed).
 * Plus: session flow keys are forgotten and the settings-origin flow
 * redirects back to the Profile Settings tab.
 */
class WhatsAppChangeNumberReplaceTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PHONE = '+15550001111';
    private const NEW_PHONE = '+15552223333';

    private function plan(): Plan
    {
        return Plan::create([
            'name'          => 'Free',
            'slug'          => 'free-' . Str::lower(Str::random(6)),
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
            'email'        => 'u' . Str::lower(Str::random(8)) . '@example.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'role'         => 'user',
            'handle'       => 'h' . Str::lower(Str::random(10)),
            'plan_id'      => $this->plan()->id,
            'onboarded_at' => now(),
        ]);

        return $user->fresh();
    }

    /**
     * User::created mirrors users.email into a primary, verified email
     * linked identifier — fetch that row instead of minting a second
     * primary (which would trip the one-primary-per-user constraint).
     */
    private function primaryEmail(User $user): LinkedIdentifier
    {
        $email = $user->linkedIdentifiers()->where('kind', 'email')->firstOrFail();
        $email->forceFill(['verified_at' => $email->verified_at ?? now(), 'is_primary' => true])->save();

        return $email->fresh();
    }

    private function oldPhone(User $user, bool $primary): LinkedIdentifier
    {
        $email = $this->primaryEmail($user);
        $row   = LinkedIdentifier::create([
            'user_id'     => $user->id,
            'kind'        => 'phone',
            'value'       => LinkedIdentifier::normalize('phone', self::OLD_PHONE),
            'verified_at' => now(),
            'is_primary'  => false,
        ]);
        if ($primary) {
            // Swap primary onto the phone (demote email first — partial
            // unique index allows only one primary per user).
            $email->forceFill(['is_primary' => false])->save();
            $row->forceFill(['is_primary' => true])->save();
        }

        return $row->fresh();
    }

    /** Issue a real OTP for the new number and POST the verify endpoint. */
    private function verifyNewNumber(User $user, array $session)
    {
        $value = LinkedIdentifier::normalize('phone', self::NEW_PHONE);
        $code  = (new OtpService())->generate($value, 'mobile', 'link', 'web');

        return $this->actingAs($user, 'web')
            ->withSession(array_merge(['whatsapp_connect_pending' => $value], $session))
            ->post('/user/onboarding/whatsapp/verify', ['code' => $code]);
    }

    public function test_replace_when_old_phone_was_primary_promotes_new_number_and_keeps_login_working(): void
    {
        $user = $this->user();
        $old  = $this->oldPhone($user, primary: true);

        $response = $this->verifyNewNumber($user, [
            'whatsapp_connect_replace' => true,
            'whatsapp_connect_return'  => 'settings',
        ]);

        // Settings-origin flow lands back on the Profile Settings tab.
        $response->assertRedirect(route('user.profile.edit'));
        $response->assertSessionHas('success');

        // Flow session keys are forgotten so a later verify can't replay.
        $response->assertSessionMissing('whatsapp_connect_pending');
        $response->assertSessionMissing('whatsapp_connect_replace');
        $response->assertSessionMissing('whatsapp_connect_return');

        // Old phone row is gone.
        $this->assertDatabaseMissing('linked_identifiers', ['id' => $old->id]);

        // New phone is linked, verified, and inherited the primary flag.
        $new = LinkedIdentifier::where('user_id', $user->id)
            ->where('kind', 'phone')
            ->where('value', LinkedIdentifier::normalize('phone', self::NEW_PHONE))
            ->first();
        $this->assertNotNull($new);
        $this->assertNotNull($new->verified_at);
        $this->assertTrue((bool) $new->is_primary);

        // Login safety: exactly one primary identifier, and it is verified.
        $primaries = $user->fresh()->linkedIdentifiers()->where('is_primary', true)->get();
        $this->assertCount(1, $primaries);
        $this->assertNotNull($primaries->first()->verified_at);
        $this->assertSame($new->id, $primaries->first()->id);

        // Only one phone remains on the account.
        $this->assertSame(1, $user->fresh()->linkedIdentifiers()->where('kind', 'phone')->count());
        $this->assertTrue($user->fresh()->hasWhatsappNumber());
    }

    public function test_replace_when_email_is_primary_removes_old_phone_and_keeps_email_primary(): void
    {
        $user  = $this->user();
        $old   = $this->oldPhone($user, primary: false);
        $email = $user->linkedIdentifiers()->where('kind', 'email')->first();

        $response = $this->verifyNewNumber($user, [
            'whatsapp_connect_replace' => true,
            'whatsapp_connect_return'  => 'settings',
        ]);

        $response->assertRedirect(route('user.profile.edit'));
        $response->assertSessionMissing('whatsapp_connect_pending');
        $response->assertSessionMissing('whatsapp_connect_replace');
        $response->assertSessionMissing('whatsapp_connect_return');

        // Old phone removed; new phone verified but NOT primary.
        $this->assertDatabaseMissing('linked_identifiers', ['id' => $old->id]);
        $new = LinkedIdentifier::where('user_id', $user->id)
            ->where('kind', 'phone')
            ->where('value', LinkedIdentifier::normalize('phone', self::NEW_PHONE))
            ->first();
        $this->assertNotNull($new);
        $this->assertNotNull($new->verified_at);
        $this->assertFalse((bool) $new->is_primary);

        // Email stays the (only) primary identifier.
        $primaries = $user->fresh()->linkedIdentifiers()->where('is_primary', true)->get();
        $this->assertCount(1, $primaries);
        $this->assertSame($email->id, $primaries->first()->id);

        $this->assertSame(1, $user->fresh()->linkedIdentifiers()->where('kind', 'phone')->count());
    }

    public function test_verify_without_replace_flag_keeps_the_old_phone(): void
    {
        $user = $this->user();
        $old  = $this->oldPhone($user, primary: false);

        $response = $this->verifyNewNumber($user, [
            'whatsapp_connect_replace' => false,
            'whatsapp_connect_return'  => null,
        ]);

        // Non-settings origin lands on the dashboard.
        $response->assertRedirect(route('user.dashboard'));

        // Nothing removed: both numbers remain, old row untouched.
        $this->assertDatabaseHas('linked_identifiers', ['id' => $old->id]);
        $this->assertSame(2, $user->fresh()->linkedIdentifiers()->where('kind', 'phone')->count());

        // Primary is still the auto-mirrored email.
        $primary = $user->fresh()->linkedIdentifiers()->where('is_primary', true)->first();
        $this->assertNotNull($primary);
        $this->assertSame('email', $primary->kind);
    }
}
