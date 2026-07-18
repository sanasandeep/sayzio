<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Permission;
use App\Modules\Admin\Models\Role;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Self-serve "add email" for email-less (mobile/WhatsApp signup) accounts
 * (Task #4767): when a user with no users.email verifies a new email via
 * the Linked identifiers OTP flow (web session or bearer-token API), the
 * verified address is adopted as the account email — users.email is set
 * and email_verified_at stamped — without touching the primary identifier
 * (the phone stays primary; one_primary_per_user must never be violated).
 *
 * Also locks down the closing of the loop: granting admin access to an
 * email-less user fails with a clear message (web + API surfaces), and
 * succeeds once the user has adopted an email.
 *
 * In non-production the OtpService issues the static code "123456".
 * Sanctum tests use a real Bearer token (Sanctum::actingAs breaks the
 * TouchSessionToken middleware).
 */
class EmaillessUserAddEmailTest extends TestCase
{
    use RefreshDatabase;

    private const DEV_OTP = '123456';

    /** A mobile/WhatsApp-only account: no email, phone is the primary identifier. */
    private function emaillessUser(): User
    {
        $user = User::factory()->create([
            'email'             => null,
            'email_verified_at' => null,
            'mobile'            => '+1555' . random_int(1000000, 9999999),
        ]);

        return $user->fresh();
    }

    private function makeAdminGuardRole(): Role
    {
        return Role::create([
            'name'  => 'Backoffice ' . Str::random(4),
            'slug'  => 'backoffice-' . Str::random(6),
            'guard' => 'admin',
        ]);
    }

    private function makeOperator(array $permSlugs = ['users.grant_admin']): Admin
    {
        $role = Role::create([
            'name'  => 'Staff ' . Str::random(4),
            'slug'  => 'staff-' . Str::random(6),
            'guard' => 'admin',
        ]);

        foreach ($permSlugs as $slug) {
            $perm = Permission::firstOrCreate(
                ['slug' => $slug],
                ['name' => $slug, 'group' => explode('.', $slug)[0] ?? 'misc'],
            );
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        return Admin::create([
            'name'     => 'Admin ' . Str::random(4),
            'email'    => 'a' . Str::lower(Str::random(8)) . '@ex.com',
            'password' => bcrypt('x'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
    }

    // ---------------------------------------------------------------
    // Web flow: add + verify an email adopts it as the account email
    // ---------------------------------------------------------------

    public function test_web_verify_adopts_email_for_emailless_account(): void
    {
        $user  = $this->emaillessUser();
        $email = 'new' . Str::lower(Str::random(8)) . '@example.com';

        $this->assertNull($user->email);

        $this->actingAs($user)
            ->post(route('user.identifiers.start'), ['kind' => 'email', 'value' => $email])
            ->assertSessionHas('status');

        $this->actingAs($user)
            ->post(route('user.identifiers.confirm'), ['code' => self::DEV_OTP])
            ->assertRedirect(route('user.identifiers.index'))
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertSame($email, $user->email);
        $this->assertNotNull($user->email_verified_at);

        // linked_identifiers stays consistent: exactly one primary (the
        // phone), the new email row verified and non-primary.
        $this->assertSame(1, $user->linkedIdentifiers()->where('is_primary', true)->count());
        $this->assertSame('phone', $user->linkedIdentifiers()->where('is_primary', true)->value('kind'));
        $row = $user->linkedIdentifiers()->where('kind', 'email')->where('value', $email)->first();
        $this->assertNotNull($row);
        $this->assertNotNull($row->verified_at);
        $this->assertFalse((bool) $row->is_primary);
    }

    public function test_web_verify_does_not_overwrite_an_existing_account_email(): void
    {
        $user     = User::factory()->create()->fresh();
        $original = $user->email;
        $extra    = 'extra' . Str::lower(Str::random(8)) . '@example.com';

        $this->actingAs($user)
            ->post(route('user.identifiers.start'), ['kind' => 'email', 'value' => $extra]);
        $this->actingAs($user)
            ->post(route('user.identifiers.confirm'), ['code' => self::DEV_OTP])
            ->assertRedirect(route('user.identifiers.index'));

        $this->assertSame($original, $user->fresh()->email);
    }

    // ---------------------------------------------------------------
    // API flow (mobile parity)
    // ---------------------------------------------------------------

    public function test_api_verify_adopts_email_for_emailless_account(): void
    {
        $user  = $this->emaillessUser();
        $email = 'api' . Str::lower(Str::random(8)) . '@example.com';
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/send', ['kind' => 'email', 'value' => $email])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/verify', [
                'kind'  => 'email',
                'value' => $email,
                'code'  => self::DEV_OTP,
            ])
            ->assertOk()
            ->assertJsonPath('data.verified', true)
            ->assertJsonPath('data.adopted_as_account_email', true);

        $user->refresh();
        $this->assertSame($email, $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, $user->linkedIdentifiers()->where('is_primary', true)->count());
    }

    public function test_adoption_is_skipped_when_another_user_row_holds_the_email(): void
    {
        // Legacy edge: a users.email holder without a linked_identifiers
        // mirror row. Adoption must skip silently (no unique-constraint 500).
        $holder = User::factory()->create()->fresh();
        $holder->linkedIdentifiers()->delete();

        $user  = $this->emaillessUser();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/send', ['kind' => 'email', 'value' => $holder->email])
            ->assertOk();

        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/verify', [
                'kind'  => 'email',
                'value' => LinkedIdentifier::normalize('email', (string) $holder->email),
                'code'  => self::DEV_OTP,
            ])
            ->assertOk()
            ->assertJsonPath('data.adopted_as_account_email', false);

        $this->assertNull($user->fresh()->email);
    }

    // ---------------------------------------------------------------
    // Admin grant: blocked while email-less, works after adding one
    // ---------------------------------------------------------------

    public function test_grant_admin_access_is_blocked_for_emailless_user_then_succeeds_after_adding_email(): void
    {
        $operator = $this->makeOperator();
        $target   = $this->emaillessUser();
        $role     = $this->makeAdminGuardRole();

        // Blocked with a clear message while the account has no email.
        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $target->id . '/admin-access', ['role_id' => $role->id])
            ->assertSessionHas('error');
        $this->assertSame(0, Admin::where('user_id', $target->id)->count());

        // The user adds + verifies an email (API path).
        $email = 'promoted' . Str::lower(Str::random(8)) . '@example.com';
        $token = $target->createToken('test')->plainTextToken;
        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/send', ['kind' => 'email', 'value' => $email])
            ->assertOk();
        $this->withToken($token)
            ->postJson('/api/v1/me/identifiers/verify', ['kind' => 'email', 'value' => $email, 'code' => self::DEV_OTP])
            ->assertOk()
            ->assertJsonPath('data.adopted_as_account_email', true);

        // Drop the lingering default Bearer header withToken() installed —
        // it would otherwise ride along on the web request below and
        // re-authenticate it as $target instead of the operator.
        $this->flushHeaders();

        // Now the grant succeeds and creates the back-office record.
        $this->actingAs($operator, 'admin')
            ->post('/admin/users/' . $target->id . '/admin-access', ['role_id' => $role->id])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('admins', [
            'email'   => $email,
            'role_id' => $role->id,
            'status'  => 'active',
            'user_id' => $target->id,
        ]);
    }

    public function test_api_grant_admin_access_is_blocked_for_emailless_user(): void
    {
        $operator = $this->makeOperator(['staff.create']);
        $target   = $this->emaillessUser();
        $role     = $this->makeAdminGuardRole();

        // The mobile admin surface authenticates as a web user bridged to a
        // back-office Admin by email (User::adminAccount).
        $bridge = User::factory()->create(['email' => $operator->email])->fresh();
        $token  = $bridge->createToken('test')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/admin/users/' . $target->id . '/admin-access', ['role_id' => $role->id])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'user_email_required');
    }
}
