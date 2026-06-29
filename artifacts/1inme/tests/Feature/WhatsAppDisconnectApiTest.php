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

    /** Attach a verified email so the account always keeps a primary contact. */
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

    private function verifyPhone(User $u, string $number = '+15551234567', bool $primary = false): LinkedIdentifier
    {
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
            ->assertJsonPath('data.remove_blocked_reason', null);
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

    public function test_cannot_disconnect_a_primary_number(): void
    {
        // Signed up with the phone (it's the primary identifier) and has no other
        // verified contact — mirrors the web guard that blocks the removal.
        $user = $this->user();
        $phone = $this->verifyPhone($user, '+15551234567', primary: true);

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
