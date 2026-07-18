<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * When a user updates their display name, the linked admin account's name
 * should be kept in sync so the admin sidebar always shows the current name.
 *
 * Covers three cases:
 *   1. A user with a linked admin updates their name → admin name updated.
 *   2. A user without any linked admin updates their name → no error, no side-effects.
 *   3. An admin with no linked user is untouched by profile updates of unrelated users.
 */
class AdminNameSyncOnProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function webProfilePayload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name'     => $user->name,
            'email'    => $user->email,
            'timezone' => 'UTC',
            'language' => 'en',
        ], $overrides);
    }

    private function makeLinkedAdmin(User $user, string $adminName = 'Original Admin Name'): Admin
    {
        return Admin::create([
            'name'    => $adminName,
            'email'   => $user->email,
            'password' => Hash::make('secret'),
            'status'  => 'active',
            'user_id' => $user->id,
        ]);
    }

    /**
     * The happy path: a user with a verified email has a linked admin record.
     * Renaming via the profile form should update the admin name too.
     */
    public function test_renaming_profile_syncs_linked_admin_name(): void
    {
        $user = User::factory()->create([
            'name'              => 'Sana Rahman',
            'email_verified_at' => now(),
        ])->fresh();

        $admin = $this->makeLinkedAdmin($user, 'Sana Rahman');

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->webProfilePayload($user, ['name' => 'Sana Sandeep'])
        );

        $resp->assertSessionHasNoErrors();
        $this->assertSame('Sana Sandeep', $user->fresh()->name);
        $this->assertSame('Sana Sandeep', $admin->fresh()->name);
    }

    /**
     * A user with no linked admin should be able to rename their profile
     * without any error or exception.
     */
    public function test_renaming_profile_without_admin_account_is_safe(): void
    {
        $user = User::factory()->create([
            'name'              => 'Plain User',
            'email_verified_at' => now(),
        ])->fresh();

        // Explicitly confirm there is no admin with this user's id.
        $this->assertNull(Admin::where('user_id', $user->id)->first());

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->webProfilePayload($user, ['name' => 'Plain User Renamed'])
        );

        $resp->assertSessionHasNoErrors();
        $this->assertSame('Plain User Renamed', $user->fresh()->name);
    }

    /**
     * An admin with no linked user record should keep their own name
     * regardless of any user profile updates happening on the platform.
     */
    public function test_unlinked_admin_name_is_not_affected_by_user_profile_updates(): void
    {
        // Staff-only admin: no user_id link.
        $staffAdmin = Admin::create([
            'name'    => 'Staff Member',
            'email'   => 'staff@example.com',
            'password' => Hash::make('secret'),
            'status'  => 'active',
        ]);

        // A completely different user (different email) renames their profile.
        $user = User::factory()->create([
            'name'              => 'Some User',
            'email'             => 'someuser@example.com',
            'email_verified_at' => now(),
        ])->fresh();

        $resp = $this->actingAs($user)->put(
            route('user.profile.update'),
            $this->webProfilePayload($user, ['name' => 'Some User Renamed'])
        );

        $resp->assertSessionHasNoErrors();
        // The unrelated staff admin's name must be unchanged.
        $this->assertSame('Staff Member', $staffAdmin->fresh()->name);
    }
}
