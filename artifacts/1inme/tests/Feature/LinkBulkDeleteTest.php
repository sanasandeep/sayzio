<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WorkspaceMember;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Services\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Bulk delete on My Links (POST user/links/delete-bulk).
 *
 * Covers: successful bulk delete with a count-aware flash, silent skipping
 * of links not owned by the workspace owner (mirroring moveBulk), and the
 * links.delete workspace-permission gate for members whose role can't
 * delete (editor).
 */
class LinkBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    private function makeLink(User $u, ?int $workspaceId = null): Link
    {
        $link = $u->links()->create([
            'user_id'   => $u->id,
            'type'      => 'url',
            'alias'     => 'bd' . substr(Str::random(10), 0, 10),
            'long_url'  => 'https://example.com',
            'is_active' => true,
        ]);
        if ($workspaceId !== null && (int) $link->workspace_id !== $workspaceId) {
            $link->forceFill(['workspace_id' => $workspaceId])->save();
        }
        return $link->fresh();
    }

    public function test_owner_can_bulk_delete_own_links(): void
    {
        $owner = $this->makeUser();
        $ws = $owner->ownedWorkspaces()->first();
        $a = $this->makeLink($owner, $ws->id);
        $b = $this->makeLink($owner, $ws->id);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $ws->id])
            ->from(route('user.links.index'))
            ->post(route('user.links.delete-bulk'), [
                'link_ids' => [$a->id, $b->id],
            ])
            ->assertRedirect(route('user.links.index'))
            ->assertSessionHas('success', '2 links deleted.');

        $this->assertDatabaseMissing('links', ['id' => $a->id]);
        $this->assertDatabaseMissing('links', ['id' => $b->id]);
    }

    public function test_links_not_owned_by_workspace_owner_are_silently_skipped(): void
    {
        $owner = $this->makeUser();
        $ws = $owner->ownedWorkspaces()->first();
        $mine = $this->makeLink($owner, $ws->id);

        // A stranger's link forced into the same workspace id: passes the
        // workspace scope but fails the ownership filter — must survive.
        $other = $this->makeUser();
        $foreign = $this->makeLink($other, $ws->id);

        $this->actingAs($owner)
            ->withSession([WorkspaceContext::SESSION_KEY => $ws->id])
            ->from(route('user.links.index'))
            ->post(route('user.links.delete-bulk'), [
                'link_ids' => [$mine->id, $foreign->id],
            ])
            ->assertRedirect(route('user.links.index'))
            ->assertSessionHas('success', '1 link deleted.');

        $this->assertDatabaseMissing('links', ['id' => $mine->id]);
        $this->assertDatabaseHas('links', ['id' => $foreign->id]);
    }

    public function test_member_without_delete_permission_is_blocked(): void
    {
        $owner = $this->makeUser();
        $ws = $owner->ownedWorkspaces()->first();
        $link = $this->makeLink($owner, $ws->id);

        // Editor role: view/create/edit but NOT links.delete.
        $member = $this->makeUser();
        WorkspaceMember::create([
            'workspace_id' => $ws->id,
            'user_id'      => $member->id,
            'role'         => 'editor',
            'permissions'  => WorkspacePermissions::roleActions()['editor'] ?? [],
        ]);

        $this->actingAs($member)
            ->withSession([WorkspaceContext::SESSION_KEY => $ws->id])
            ->post(route('user.links.delete-bulk'), [
                'link_ids' => [$link->id],
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('links', ['id' => $link->id]);
    }
}
