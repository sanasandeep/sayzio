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
 * Locks down the mobile WhatsApp number management API (Task #2776): a creator
 * can read the connected number (masked), and disconnect it through the same
 * guards the web Account Settings linked-identifier remove path uses (can't drop
 * a primary identifier or the last verified email/phone).
 *
 * Sanctum API tests authenticate with a real Bearer token (Sanctum::actingAs
 * breaks the TouchSessionToken middleware).
 */
class WhatsAppDisconnectApiTest extends TestCase
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

    private function user(array $settings = []): User
    {
        $user = User::create([
            'name'         => 'u' . Str::random(4),
            'email'        => 'u' . Str::random(8) . '@example.com',
            'password'     => Hash::make('x'),
            'status'       => 'active',
            'role'         => 'user',
            'handle'       => 'h' . Str::lower(Str::random(10)),
            'plan_id'      => $this->plan()->id,
            'settings'     => $settings,
            'onboarded_at' => now(),
        ]);
        return $user->fresh();
    }

    /**
     * The verified primary email identifier auto-created by the User::created
     * observer from users.email (creating a second primary would violate the
     * one_primary_per_user constraint).
     */
    private function verifyEmail(User $u): LinkedIdentifier
    {
        return $u->linkedIdentifiers()->where('kind', 'email')->firstOrFail();
    }

    private function verifyPhone(User $u, string $number = '+15551234567', bool $primary = false): LinkedIdentifier
    {
        if ($primary) {
            // Only one primary identifier per user — demote the auto-created
            // primary email before making the phone primary.
            $u->linkedIdentifiers()->where('is_primary', true)->update(['is_primary' => false]);
        }

        return LinkedIdentifier::create([
            'user_id'     => $u->id,
            'kind'        => 'phone',
            'value'       => $number,
            'verified_at' => now(),
            'is_primary'  => $primary,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    public function test_status_reports_no_number_when_none_connected(): void
    {
        $user = $this->user();
        $this->verifyEmail($user);

        $this->withToken($this->token($user))
            ->getJson('/api/v1/me/whatsapp')
            ->assertOk()
            ->assertJsonPath('data.has_whatsapp_number', false)
            ->assertJsonPath('data.mobile_masked', null)
            ->assertJsonPath('data.can_remove', false);
    }

    public function test_status_returns_masked_number_and_can_remove(): void
    {
        $user = $this->user();
        $this->verifyEmail($user);
        $this->verifyPhone($user, '+15551234567');

        $this->withToken($this->token($user))
            ->getJson('/api/v1/me/whatsapp')
            ->assertOk()
            ->assertJsonPath('data.has_whatsapp_number', true)
            ->assertJsonPath('data.mobile_masked', '+•••••••4567')
            ->assertJsonPath('data.can_remove', true)
            ->assertJsonPath('data.remove_blocked_reason', null)
            ->assertJsonPath('data.is_primary', false)
            ->assertJsonPath('data.promotes_to', null)
            ->assertJsonPath('data.promotes_to_kind', null);
    }

    public function test_status_exposes_promotion_target_when_number_is_primary(): void
    {
        // Phone is the primary sign-in identifier and a verified email exists:
        // status must tell the client which (masked) contact becomes primary
        // so the remove confirmation can warn about the sign-in switch.
        $user = $this->user();
        LinkedIdentifier::create([
            'user_id'     => $user->id,
            'kind'        => 'email',
            'value'       => $user->email,
            'verified_at' => now(),
            'is_primary'  => false,
        ]);
        $this->verifyPhone($user, '+15551234567', primary: true);

        $res = $this->withToken($this->token($user))
            ->getJson('/api/v1/me/whatsapp')
            ->assertOk()
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.promotes_to_kind', 'email');

        $masked = $res->json('data.promotes_to');
        $this->assertIsString($masked);
        // Masked, not the raw address: first char + bullets + @domain.
        $this->assertStringContainsString('•', $masked);
        $this->assertStringNotContainsString(substr($user->email, 1, 4), explode('@', $masked)[0]);
        $this->assertStringEndsWith('@' . explode('@', $user->email, 2)[1], $masked);
    }

    public function test_disconnect_removes_the_number(): void
    {
        $user = $this->user();
        $this->verifyEmail($user);
        $phone = $this->verifyPhone($user, '+15551234567');

        $this->withToken($this->token($user))
            ->deleteJson('/api/v1/me/whatsapp')
            ->assertOk()
            ->assertJsonPath('data.has_whatsapp_number', false)
            ->assertJsonPath('data.mobile_masked', null);

        $this->assertDatabaseMissing('linked_identifiers', ['id' => $phone->id]);
        $this->assertFalse($user->fresh()->hasWhatsappNumber());
    }

    public function test_disconnect_without_a_number_fails(): void
    {
        $user = $this->user();
        $this->verifyEmail($user);

        $this->withToken($this->token($user))
            ->deleteJson('/api/v1/me/whatsapp')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'no_number');
    }

    public function test_disconnect_primary_number_promotes_fallback_contact(): void
    {
        // Signed up with the phone (it's the primary identifier) but also has a
        // verified email — mirrors the web flow that promotes the email to
        // primary before unlinking the number.
        $user = $this->user();
        $email = LinkedIdentifier::create([
            'user_id'     => $user->id,
            'kind'        => 'email',
            'value'       => $user->email,
            'verified_at' => now(),
            'is_primary'  => false,
        ]);
        $phone = $this->verifyPhone($user, '+15551234567', primary: true);

        $status = $this->withToken($this->token($user))
            ->getJson('/api/v1/me/whatsapp')
            ->assertOk();
        $status->assertJsonPath('data.can_remove', true);
        $status->assertJsonPath('data.remove_blocked_reason', null);

        $this->withToken($this->token($user))
            ->deleteJson('/api/v1/me/whatsapp')
            ->assertOk()
            ->assertJsonPath('data.has_whatsapp_number', false)
            ->assertJsonPath('data.mobile_masked', null);

        $this->assertDatabaseMissing('linked_identifiers', ['id' => $phone->id]);
        $this->assertTrue((bool) $email->fresh()->is_primary);
        $this->assertFalse($user->fresh()->hasWhatsappNumber());
    }

    public function test_cannot_disconnect_a_primary_number_without_other_contact(): void
    {
        // Signed up with the phone (it's the primary identifier) and has no other
        // verified contact — mirrors the web guard that blocks the removal.
        // Drop the auto-created email identifier so the phone is truly the only
        // verified contact.
        $user = $this->user();
        $phone = $this->verifyPhone($user, '+15551234567', primary: true);
        $user->linkedIdentifiers()->where('kind', 'email')->delete();

        $status = $this->withToken($this->token($user))
            ->getJson('/api/v1/me/whatsapp')
            ->assertOk();
        $status->assertJsonPath('data.can_remove', false);
        $this->assertNotNull($status->json('data.remove_blocked_reason'));

        $this->withToken($this->token($user))
            ->deleteJson('/api/v1/me/whatsapp')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'cannot_remove');

        $this->assertDatabaseHas('linked_identifiers', ['id' => $phone->id]);
    }
}
