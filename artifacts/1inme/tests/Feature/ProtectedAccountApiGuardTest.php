<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\AdminActionAudit;
use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Admin\Models\Role;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Mobile (Sanctum bearer-token) parity for the protected-account guard
 * (see {@see \Tests\Feature\ProtectedAccountGuardTest} for the web side).
 *
 * On the admin API a mobile operator's authority comes from their
 * email-linked back-office {@see Admin} record (the dashboard "switch").
 * The one destructive account path that surface exposes is
 * {@see \App\Modules\Api\Controllers\AdminAccessController::revokeAdminAccess},
 * which deletes a user's linked Admin record. For a protected account that
 * would silently strip its protection, so the guard must bail there too —
 * returning the unified `{error:{message,code}}` envelope and writing the
 * same DELETE_BLOCKED audit row the web destroy guard writes.
 *
 * Authenticated requests use a real personal access token (NOT
 * Sanctum::actingAs, which breaks the TouchSessionToken middleware).
 */
class ProtectedAccountApiGuardTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function superAdminRole(): Role
    {
        return Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin']
        );
    }

    /**
     * Build a fully-provisioned web User (active, onboarded, personal
     * workspace). The canonical {@see User} model now ships a factory
     * ({@see \Database\Factories\UserDatabaseFactory}) that provisions the
     * default workspace, so this just delegates to it.
     */
    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs)->fresh();
    }

    /** A web User bridged (by email) to an active super-admin back-office Admin. */
    private function makeOperator(): User
    {
        $email = 'op' . Str::random(8) . '@example.com';
        Admin::create([
            'name'     => 'Operator',
            'email'    => $email,
            'password' => Hash::make('secret'),
            'role_id'  => $this->superAdminRole()->id,
            'status'   => 'active',
        ]);
        return $this->makeUser(['email' => $email]);
    }

    /**
     * A target user that has a linked back-office Admin record (so
     * revokeAdminAccess has something to delete), optionally on the
     * protected list.
     */
    private function makeUserWithAdminAccess(string $email, bool $protected): User
    {
        Admin::create([
            'name'     => 'Target ' . Str::random(4),
            'email'    => $email,
            'password' => Hash::make('secret'),
            'role_id'  => $this->superAdminRole()->id,
            'status'   => 'active',
        ]);
        $user = $this->makeUser(['email' => $email]);

        if ($protected) {
            ProtectedAccount::create([
                'email'  => ProtectedAccount::normalizeEmail($email),
                'locked' => false,
                'label'  => 'Test',
            ]);
        }

        return $user;
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function assertDeleteBlockedLogged(string $email): void
    {
        $audit = AdminActionAudit::where('action', AdminActionLogger::DELETE_BLOCKED)
            ->latest('id')
            ->first();
        $this->assertNotNull($audit, 'Expected a DELETE_BLOCKED audit row.');

        $needle = strtolower(trim($email));
        $haystack = [
            strtolower(trim((string) $audit->target_email)),
            strtolower(trim((string) ($audit->details['email'] ?? ''))),
        ];
        $this->assertContains($needle, $haystack, "DELETE_BLOCKED audit did not reference {$email}.");
    }

    // ---------------------------------------------------------------
    // Revoke admin access: blocked for a protected account
    // ---------------------------------------------------------------

    public function test_revoking_a_protected_accounts_admin_access_is_blocked_and_logged(): void
    {
        $operator = $this->makeOperator();
        $target   = $this->makeUserWithAdminAccess('protected-revoke@ex.com', protected: true);

        $resp = $this->withToken($this->token($operator))
            ->deleteJson("/api/v1/admin/users/{$target->id}/admin-access");

        // Unified error envelope, same shape every API failure uses.
        $resp->assertStatus(422);
        $resp->assertJsonPath('error.code', 'account_protected');
        $this->assertNotEmpty($resp->json('error.message'));

        // The linked Admin record survives — protection held.
        $this->assertDatabaseHas('admins', ['email' => 'protected-revoke@ex.com']);
        $this->assertNotNull($target->fresh()->adminAccount());

        $this->assertDeleteBlockedLogged('protected-revoke@ex.com');
    }

    public function test_protection_matches_email_case_insensitively_over_the_api(): void
    {
        $operator = $this->makeOperator();
        // Protected entry stored lowercased; the account's email differs only
        // by case, so the guard must still recognise it.
        ProtectedAccount::create([
            'email'  => 'caseapi@example.com',
            'locked' => false,
            'label'  => 'Test',
        ]);
        Admin::create([
            'name'     => 'Case Target',
            'email'    => 'CaseApi@Example.COM',
            'password' => Hash::make('secret'),
            'role_id'  => $this->superAdminRole()->id,
            'status'   => 'active',
        ]);
        $target = $this->makeUser(['email' => 'CaseApi@Example.COM']);

        $this->withToken($this->token($operator))
            ->deleteJson("/api/v1/admin/users/{$target->id}/admin-access")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'account_protected');

        $this->assertNotNull($target->fresh()->adminAccount());
        $this->assertDeleteBlockedLogged('caseapi@example.com');
    }

    // ---------------------------------------------------------------
    // Control: an ordinary account is still revocable
    // ---------------------------------------------------------------

    public function test_revoking_an_unprotected_accounts_admin_access_succeeds(): void
    {
        // Control: the guard must not blanket-block ordinary revocations.
        $operator = $this->makeOperator();
        $target   = $this->makeUserWithAdminAccess('plain-revoke@ex.com', protected: false);

        $this->withToken($this->token($operator))
            ->deleteJson("/api/v1/admin/users/{$target->id}/admin-access")
            ->assertOk();

        $this->assertDatabaseMissing('admins', ['email' => 'plain-revoke@ex.com']);
        $this->assertNull($target->fresh()->adminAccount());

        $this->assertNull(
            AdminActionAudit::where('action', AdminActionLogger::DELETE_BLOCKED)->first(),
            'An unprotected revoke must not write a DELETE_BLOCKED audit.'
        );
    }
}
