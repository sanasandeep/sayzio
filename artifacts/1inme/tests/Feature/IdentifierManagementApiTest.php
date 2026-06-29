<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Locks down the mobile "Linked identifiers" management API (Task #2785,
 * built in Task #2779): list every verified email/phone/social, add +
 * verify a new email/phone, promote a verified one to primary, and remove
 * a non-primary one — all reusing the AccountMergeService guards.
 *
 * The guards here are safety-critical: a bug could leave an account with no
 * way to sign in (no primary contact, or no verified email/phone at all).
 * These tests drive the real stateless HTTP path end-to-end and assert that
 * a user can never lock themselves out:
 *   - the primary identifier can't be removed,
 *   - the last verified email/phone can't be removed,
 *   - an unverified or social identifier can't be promoted to primary.
 * The happy path proves the intended escape hatch: add + verify a new
 * contact, promote it, then drop the old one.
 *
 * Sanctum API tests authenticate with a real Bearer token (Sanctum::actingAs
 * breaks the TouchSessionToken middleware → every authed request would 500).
 * In non-production the OtpService issues the static code "123456".
 *
 * Note: User::created auto-attaches a primary, verified email identifier
 * (derived from users.email). Tests fetch that row via {@see primaryEmail}
 * rather than creating their own — a second is_primary row would violate the
 * `one_primary_per_user` unique index.
 */
class IdentifierManagementApiTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_OTP = '123456';

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

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
     * The primary, verified email identifier that User::created auto-attaches.
     * Returned (not re-created) so we never violate one_primary_per_user.
     */
    private function primaryEmail(User $u): LinkedIdentifier
    {
        $row = $u->linkedIdentifiers()
            ->where('kind', 'email')
            ->where('value', LinkedIdentifier::normalize('email', (string) $u->email))
            ->first();

        // Defensive fallback in case the creation hook is ever disabled —
        // the suite should still set up a primary email rather than 500.
        if (!$row) {
            $row = LinkedIdentifier::create([
                'user_id'     => $u->id,
                'kind'        => 'email',
                'value'       => LinkedIdentifier::normalize('email', (string) $u->email),
                'verified_at' => now(),
                'is_primary'  => true,
            ]);
        }

        return $row;
    }

    private function phone(User $u, string $number = '+15551234567', bool $primary = false, bool $verified = true): LinkedIdentifier
    {
        return LinkedIdentifier::create([
            'user_id'     => $u->id,
            'kind'        => 'phone',
            'value'       => $number,
            'verified_at' => $verified ? now() : null,
            'is_primary'  => $primary,
        ]);
    }

    private function social(User $u, bool $primary = false): LinkedIdentifier
    {
        $ext = 'sid' . Str::lower(Str::random(8));
        return LinkedIdentifier::create([
            'user_id'     => $u->id,
            'kind'        => 'social',
            'value'       => 'google:' . $ext,
            'provider'    => 'google',
            'external_id' => $ext,
            'verified_at' => now(),
            'is_primary'  => $primary,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    // ---------------------------------------------------------------
    // Auth gate
    // ---------------------------------------------------------------

    public function test_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/me/identifiers')->assertStatus(401);
    }

    // ---------------------------------------------------------------
    // GET — list with per-row eligibility
    // ---------------------------------------------------------------

    public function test_index_lists_identifiers_with_eligibility_flags(): void
    {
        $user = $this->user();
        $email = $this->primaryEmail($user);
        $phone = $this->phone($user);

        $res = $this->withToken($this->token($user))
            ->getJson('/api/v1/me/identifiers')
            ->assertOk()
            ->assertJsonPath('data.addable_kinds', ['email', 'phone']);

        $byId = collect($res->json('data.identifiers'))->keyBy('id');

        // Primary email: can't be removed (it's primary), can't be promoted
        // (already primary).
        $this->assertSame(true, $byId[$email->id]['is_primary']);
        $this->assertFalse($byId[$email->id]['can_remove']);
        $this->assertNotNull($byId[$email->id]['remove_blocked_reason']);
        $this->assertFalse($byId[$email->id]['can_promote']);

        // Secondary verified phone: removable (email keeps the account alive)
        // and promotable.
        $this->assertTrue($byId[$phone->id]['can_remove']);
        $this->assertNull($byId[$phone->id]['remove_blocked_reason']);
        $this->assertTrue($byId[$phone->id]['can_promote']);
        $this->assertNull($byId[$phone->id]['promote_blocked_reason']);
    }

    // ---------------------------------------------------------------
    // POST send + verify — adding a new identifier
    // ---------------------------------------------------------------

    public function test_send_then_verify_links_a_new_phone(): void
    {
        $user = $this->user();
        $this->primaryEmail($user);
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/send', [
                'kind'  => 'phone',
                'value' => '+1 (555) 987-6543',
            ])
            ->assertOk()
            ->assertJsonPath('data.sent', true)
            ->assertJsonPath('data.kind', 'phone')
            // Value comes back normalised (formatting stripped).
            ->assertJsonPath('data.value', '+15559876543');

        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/verify', [
                'kind'  => 'phone',
                'value' => '+15559876543',
                'code'  => self::DEV_OTP,
            ])
            ->assertOk()
            ->assertJsonPath('data.verified', true);

        $this->assertDatabaseHas('linked_identifiers', [
            'user_id'    => $user->id,
            'kind'       => 'phone',
            'value'      => '+15559876543',
            'is_primary' => false,
        ]);
        $this->assertNotNull(
            $user->linkedIdentifiers()->where('value', '+15559876543')->first()->verified_at,
        );
    }

    public function test_verify_with_wrong_code_does_not_link(): void
    {
        $user = $this->user();
        $this->primaryEmail($user);
        $token = $this->token($user);

        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/send', [
                'kind' => 'phone', 'value' => '+15559876543',
            ])->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/verify', [
                'kind' => 'phone', 'value' => '+15559876543', 'code' => '000000',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_code');

        $this->assertDatabaseMissing('linked_identifiers', [
            'user_id' => $user->id, 'value' => '+15559876543',
        ]);
    }

    public function test_send_rejects_an_identifier_already_linked_to_another_account(): void
    {
        $other = $this->user();
        $this->phone($other, '+15550001111');

        $user = $this->user();
        $this->primaryEmail($user);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/me/identifiers/send', [
                'kind' => 'phone', 'value' => '+15550001111',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'in_use');
    }

    // ---------------------------------------------------------------
    // DELETE — guard failures that would lock the user out
    // ---------------------------------------------------------------

    public function test_cannot_remove_the_primary_identifier(): void
    {
        $user = $this->user();
        $email = $this->primaryEmail($user);
        // A second verified contact exists, so the ONLY thing blocking the
        // removal is that this is the primary — proves the primary guard
        // fires independently of the "last contact" guard.
        $this->phone($user);

        $this->withToken($this->token($user))
            ->deleteJson('/api/v1/me/identifiers/' . $email->id)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'cannot_remove');

        $this->assertDatabaseHas('linked_identifiers', ['id' => $email->id]);
    }

    public function test_cannot_remove_the_last_verified_contact(): void
    {
        // Edge state: the only primary is a social identity (defence-in-depth
        // — the promote guard normally prevents this) and the sole verified
        // email/phone is a non-primary phone. Removing the phone clears the
        // last way to actually sign in, so the "keep at least one verified
        // email or phone" guard must fire even though a (social) identifier
        // would remain.
        $user = $this->user();
        $email = $this->primaryEmail($user);
        $social = $this->social($user);
        $phone = $this->phone($user, '+15551234567', primary: false);

        // Drop the auto email and hand the primary flag to the social row so
        // the phone is the only remaining contact.
        DB::table('linked_identifiers')->where('id', $email->id)->delete();
        DB::table('linked_identifiers')->where('id', $social->id)->update(['is_primary' => true]);

        $this->withToken($this->token($user))
            ->deleteJson('/api/v1/me/identifiers/' . $phone->id)
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'cannot_remove')
            ->assertJsonPath(
                'error.message',
                'You must keep at least one verified email or phone.',
            );

        $this->assertDatabaseHas('linked_identifiers', ['id' => $phone->id]);
    }

    public function test_destroy_returns_404_for_an_identifier_owned_by_someone_else(): void
    {
        $owner = $this->user();
        $foreign = $this->phone($owner, '+15557778888');

        $user = $this->user();
        $this->primaryEmail($user);

        $this->withToken($this->token($user))
            ->deleteJson('/api/v1/me/identifiers/' . $foreign->id)
            ->assertStatus(404);

        $this->assertDatabaseHas('linked_identifiers', ['id' => $foreign->id]);
    }

    // ---------------------------------------------------------------
    // POST promote — guard failures
    // ---------------------------------------------------------------

    public function test_cannot_promote_an_unverified_identifier(): void
    {
        $user = $this->user();
        $this->primaryEmail($user);
        $unverified = $this->phone($user, '+15551112222', verified: false);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/me/identifiers/' . $unverified->id . '/promote')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'cannot_promote');

        // Primary unchanged.
        $this->assertFalse((bool) $unverified->fresh()->is_primary);
    }

    public function test_cannot_promote_a_social_identifier(): void
    {
        $user = $this->user();
        $this->primaryEmail($user);
        $social = $this->social($user);

        $this->withToken($this->token($user))
            ->postJson('/api/v1/me/identifiers/' . $social->id . '/promote')
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'cannot_promote');

        $this->assertFalse((bool) $social->fresh()->is_primary);
    }

    // ---------------------------------------------------------------
    // Happy path: add + verify a new identifier, promote it, drop the old one
    // ---------------------------------------------------------------

    public function test_promote_then_remove_old_primary_without_lockout(): void
    {
        $user = $this->user();
        $oldPrimary = $this->primaryEmail($user);
        $token = $this->token($user);

        // 1. Add + verify a brand-new phone.
        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/send', [
                'kind' => 'phone', 'value' => '+15559876543',
            ])->assertOk();
        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/verify', [
                'kind' => 'phone', 'value' => '+15559876543', 'code' => self::DEV_OTP,
            ])->assertOk();

        $newPhone = $user->fresh()->linkedIdentifiers()
            ->where('value', '+15559876543')->firstOrFail();

        // New phone is verified but not yet primary.
        $this->assertFalse((bool) $newPhone->is_primary);

        // 2. Promote the new phone to primary.
        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/' . $newPhone->id . '/promote')
            ->assertOk()
            ->assertJsonPath('data.promoted', true);

        $this->assertTrue((bool) $newPhone->fresh()->is_primary);
        $this->assertFalse((bool) $oldPrimary->fresh()->is_primary);
        // The user row's primary contact column tracked the promotion.
        $this->assertSame('+15559876543', $user->fresh()->mobile);

        // 3. Now the old email is no longer primary → it can be removed.
        $this->withToken($token)
            ->deleteJson('/api/v1/me/identifiers/' . $oldPrimary->id)
            ->assertOk()
            ->assertJsonPath('data.removed', true);

        $this->assertDatabaseMissing('linked_identifiers', ['id' => $oldPrimary->id]);
        // Account still has exactly one verified, primary contact — not
        // locked out.
        $remaining = $user->fresh()->verifiedIdentifiers()->get();
        $this->assertCount(1, $remaining);
        $this->assertTrue((bool) $remaining->first()->is_primary);
    }
}
