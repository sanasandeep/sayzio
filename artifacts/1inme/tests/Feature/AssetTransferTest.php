<?php

namespace Tests\Feature;

use App\Modules\Admin\Models\Admin;
use App\Modules\User\Models\AssetTransfer;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\AssetTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Admin-granted link & workspace transfer (Task #5550).
 *
 * Covers the capability gate (explicit grant + implicit admin-email
 * match), the service-level guards (self-transfer, non-owned), the full
 * link / workspace ownership handoff, the admin grant/revoke toggle, and
 * the /api/v1 parity endpoints.
 *
 * Authenticated API requests use a real personal access token (NOT
 * Sanctum::actingAs, which breaks the TouchSessionToken middleware).
 * Links are created via Link::create — the model has no factory.
 */
class AssetTransferTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------

    private function makeUser(array $attrs = []): User
    {
        return User::factory()->create($attrs);
    }

    private function grant(User $user): User
    {
        $user->forceFill(['transfer_capability_granted_at' => now()])->save();

        return $user->refresh();
    }

    private function makeLink(User $owner, array $attrs = []): Link
    {
        // workspace_id is not mass-assignable (BelongsToWorkspace fills it
        // from the bound current_workspace in real requests), so bind it
        // explicitly after create.
        $workspaceId = $attrs['workspace_id'] ?? $owner->ensureDefaultWorkspace()->id;
        unset($attrs['workspace_id']);

        $link = Link::create(array_merge([
            'user_id'  => $owner->id,
            'type'     => 'short',
            'alias'    => 'xfer' . uniqid(),
            'long_url' => 'https://example.com',
        ], $attrs));
        \Illuminate\Support\Facades\DB::table('links')
            ->where('id', $link->id)
            ->update(['workspace_id' => $workspaceId]);

        return $link->refresh();
    }

    private function makeTeamWorkspace(User $owner, string $name = 'Team WS'): Workspace
    {
        return Workspace::create([
            'owner_user_id' => $owner->id,
            'name'          => $name,
            'is_personal'   => false,
        ]);
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    // ---------------------------------------------------------------
    // Capability
    // ---------------------------------------------------------------

    public function test_capability_defaults_off_and_grant_turns_it_on(): void
    {
        $user = $this->makeUser();
        $this->assertFalse($user->canTransferAssets());

        $this->grant($user);
        $this->assertTrue($user->canTransferAssets());
    }

    public function test_admin_email_match_grants_capability_implicitly(): void
    {
        $user = $this->makeUser(['email' => 'boss@example.com']);
        Admin::query()->create([
            'name'     => 'Boss',
            'email'    => 'Boss@Example.com',
            'password' => bcrypt('secret-password-1'),
        ]);

        $this->assertTrue($user->refresh()->canTransferAssets());
    }

    // ---------------------------------------------------------------
    // Service guards
    // ---------------------------------------------------------------

    public function test_ungranted_user_cannot_transfer(): void
    {
        $sender    = $this->makeUser();
        $recipient = $this->makeUser();
        $link      = $this->makeLink($sender);

        $this->expectException(\RuntimeException::class);
        app(AssetTransferService::class)->transferLink($link, $sender, $recipient);
    }

    public function test_self_transfer_rejected(): void
    {
        $sender = $this->grant($this->makeUser());
        $link   = $this->makeLink($sender);

        $this->expectException(\InvalidArgumentException::class);
        app(AssetTransferService::class)->transferLink($link, $sender, $sender);
    }

    public function test_non_owned_link_rejected(): void
    {
        $sender    = $this->grant($this->makeUser());
        $stranger  = $this->makeUser();
        $recipient = $this->makeUser();
        $link      = $this->makeLink($stranger);

        $this->expectException(\RuntimeException::class);
        app(AssetTransferService::class)->transferLink($link, $sender, $recipient);
    }

    public function test_non_owned_workspace_rejected(): void
    {
        $sender    = $this->grant($this->makeUser());
        $stranger  = $this->makeUser();
        $recipient = $this->makeUser();
        $ws        = $this->makeTeamWorkspace($stranger);

        $this->expectException(\RuntimeException::class);
        app(AssetTransferService::class)->transferWorkspace($ws, $sender, $recipient);
    }

    // ---------------------------------------------------------------
    // Link transfer
    // ---------------------------------------------------------------

    public function test_link_transfer_moves_ownership_and_audits_and_notifies(): void
    {
        $sender    = $this->grant($this->makeUser());
        $recipient = $this->makeUser();
        $link      = $this->makeLink($sender);

        $transfer = app(AssetTransferService::class)->transferLink($link, $sender, $recipient, 'web');

        $link->refresh();
        $this->assertSame($recipient->id, (int) $link->user_id);
        $this->assertSame($recipient->ensureDefaultWorkspace()->id, (int) $link->workspace_id);

        $this->assertDatabaseHas('asset_transfers', [
            'id'           => $transfer->id,
            'kind'         => AssetTransfer::KIND_LINK,
            'asset_id'     => $link->id,
            'from_user_id' => $sender->id,
            'to_user_id'   => $recipient->id,
            'channel'      => 'web',
        ]);

        // Both parties get an in-app notification.
        $this->assertTrue(
            UserNotification::where('user_id', $sender->id)->where('type', 'asset_transfer')->exists()
        );
        $this->assertTrue(
            UserNotification::where('user_id', $recipient->id)->where('type', 'asset_transfer')->exists()
        );
    }

    // ---------------------------------------------------------------
    // Workspace transfer
    // ---------------------------------------------------------------

    public function test_workspace_transfer_moves_workspace_and_links(): void
    {
        $sender    = $this->grant($this->makeUser());
        $recipient = $this->makeUser();
        $ws        = $this->makeTeamWorkspace($sender);
        $link      = $this->makeLink($sender, ['workspace_id' => $ws->id]);

        app(AssetTransferService::class)->transferWorkspace($ws, $sender, $recipient, 'web');

        $ws->refresh();
        $link->refresh();
        $this->assertSame($recipient->id, (int) $ws->owner_user_id);
        $this->assertFalse((bool) $ws->is_personal);
        $this->assertSame($recipient->id, (int) $link->user_id);
        $this->assertSame($ws->id, (int) $link->workspace_id);

        // Sender still has a default workspace of their own.
        $this->assertTrue(
            Workspace::where('owner_user_id', $sender->id)->exists()
        );
    }

    public function test_workspace_transfer_moves_links_not_owned_by_sender(): void
    {
        $sender    = $this->grant($this->makeUser());
        $recipient = $this->makeUser();
        $member    = $this->makeUser();
        $ws        = $this->makeTeamWorkspace($sender);
        $ownLink   = $this->makeLink($sender, ['workspace_id' => $ws->id]);
        $memberLink = $this->makeLink($member, ['workspace_id' => $ws->id]);

        app(AssetTransferService::class)->transferWorkspace($ws, $sender, $recipient, 'web');

        $this->assertSame($recipient->id, (int) $ownLink->refresh()->user_id);
        $this->assertSame($recipient->id, (int) $memberLink->refresh()->user_id);
        $this->assertSame($ws->id, (int) $memberLink->workspace_id);
        $this->assertSame($recipient->id, (int) $ws->refresh()->owner_user_id);
    }

    public function test_workspace_transfer_when_recipient_already_member(): void
    {
        $sender    = $this->grant($this->makeUser());
        $recipient = $this->makeUser();
        $ws        = $this->makeTeamWorkspace($sender);
        \Illuminate\Support\Facades\DB::table('workspace_members')->insert([
            'workspace_id' => $ws->id,
            'user_id'      => $recipient->id,
            'role'         => 'member',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        app(AssetTransferService::class)->transferWorkspace($ws, $sender, $recipient, 'web');

        $this->assertSame($recipient->id, (int) $ws->refresh()->owner_user_id);
        // No leftover member rows for either party (recipient is now owner).
        $this->assertSame(0, \Illuminate\Support\Facades\DB::table('workspace_members')
            ->where('workspace_id', $ws->id)
            ->whereIn('user_id', [$sender->id, $recipient->id])
            ->count());
    }

    // ---------------------------------------------------------------
    // Web routes
    // ---------------------------------------------------------------

    public function test_web_link_transfer_route(): void
    {
        $sender    = $this->grant($this->makeUser());
        $recipient = $this->makeUser();
        $link      = $this->makeLink($sender);

        $res = $this->actingAs($sender, 'web')->post(
            route('user.links.transfer', $link),
            ['recipient_email' => $recipient->email],
        );

        $res->assertRedirect(route('user.links.index'));
        $res->assertSessionHas('success');
        $this->assertSame($recipient->id, (int) $link->refresh()->user_id);
    }

    public function test_web_transfer_unknown_recipient_errors(): void
    {
        $sender = $this->grant($this->makeUser());
        $link   = $this->makeLink($sender);

        $res = $this->actingAs($sender, 'web')
            ->from(route('user.links.index'))
            ->post(route('user.links.transfer', $link), [
                'recipient_email' => 'nobody@nowhere.example',
            ]);

        $res->assertRedirect(route('user.links.index'));
        $res->assertSessionHas('error');
        $this->assertSame($sender->id, (int) $link->refresh()->user_id);
    }

    // ---------------------------------------------------------------
    // Admin grant/revoke
    // ---------------------------------------------------------------

    public function test_admin_can_grant_and_revoke_capability(): void
    {
        $role = \App\Modules\Admin\Models\Role::firstOrCreate(
            ['slug' => 'super-admin'],
            ['name' => 'Super Admin', 'guard' => 'admin'],
        );
        $admin = Admin::query()->create([
            'name'     => 'Root',
            'email'    => 'root-' . uniqid() . '@example.com',
            'password' => bcrypt('secret-password-1'),
            'role_id'  => $role->id,
            'status'   => 'active',
        ]);
        $user = $this->makeUser();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.transfer-capability', $user), ['grant' => 1])
            ->assertRedirect();
        $this->assertTrue($user->refresh()->canTransferAssets());
        $this->assertNotNull($user->transfer_capability_granted_at);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.transfer-capability', $user), ['grant' => 0])
            ->assertRedirect();
        $this->assertNull($user->refresh()->transfer_capability_granted_at);
        $this->assertFalse($user->canTransferAssets());
    }

    // ---------------------------------------------------------------
    // API parity
    // ---------------------------------------------------------------

    public function test_api_capability_probe(): void
    {
        $user = $this->makeUser();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->getJson('/api/v1/me/transfer-capability')
            ->assertOk()
            ->assertJsonPath('data.can_transfer', false);

        $this->grant($user);
        $this->flushHeaders();

        $this->withHeader('Authorization', 'Bearer ' . $this->token($user))
            ->getJson('/api/v1/me/transfer-capability')
            ->assertOk()
            ->assertJsonPath('data.can_transfer', true);
    }

    public function test_api_link_transfer(): void
    {
        $sender    = $this->grant($this->makeUser());
        $recipient = $this->makeUser();
        $link      = $this->makeLink($sender);

        $this->withHeader('Authorization', 'Bearer ' . $this->token($sender))
            ->postJson("/api/v1/links/{$link->id}/transfer", [
                'recipient_email' => $recipient->email,
            ])
            ->assertOk()
            ->assertJsonPath('data.kind', 'link')
            ->assertJsonPath('data.asset_id', $link->id);

        $this->assertSame($recipient->id, (int) $link->refresh()->user_id);
        $this->assertDatabaseHas('asset_transfers', [
            'asset_id' => $link->id,
            'channel'  => 'api',
        ]);
    }

    public function test_api_workspace_transfer_and_guards(): void
    {
        $sender    = $this->grant($this->makeUser());
        $recipient = $this->makeUser();
        $ws        = $this->makeTeamWorkspace($sender);

        // Self-transfer rejected with 422.
        $this->withHeader('Authorization', 'Bearer ' . $this->token($sender))
            ->postJson("/api/v1/workspaces/{$ws->id}/transfer", [
                'recipient_email' => $sender->email,
            ])
            ->assertStatus(422);
        $this->flushHeaders();

        // Ungranted user rejected with 403.
        $other   = $this->makeUser();
        $otherWs = $this->makeTeamWorkspace($other);
        $this->withHeader('Authorization', 'Bearer ' . $this->token($other))
            ->postJson("/api/v1/workspaces/{$otherWs->id}/transfer", [
                'recipient_email' => $recipient->email,
            ])
            ->assertStatus(403);
        $this->flushHeaders();

        // Happy path.
        $this->withHeader('Authorization', 'Bearer ' . $this->token($sender))
            ->postJson("/api/v1/workspaces/{$ws->id}/transfer", [
                'recipient_email' => $recipient->email,
            ])
            ->assertOk()
            ->assertJsonPath('data.kind', 'workspace');

        $this->assertSame($recipient->id, (int) $ws->refresh()->owner_user_id);
    }
}
